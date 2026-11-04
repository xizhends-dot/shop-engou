<?php
/**
 * 远程图片导入（PHP 7.4+）。不读取配置，不接受任意本地路径作为下载 URL。
 * deadline 为 microtime(true) 同一时间基准的绝对截止时间（秒）。
 * URL/IP/内容保存函数可独立做无网络测试。
 */
require_once __DIR__ . '/store.php';

/** 只接受 ASCII 公网域名；返回规范化 URL、host、scheme、port，失败返回 false。 */
function remote_image_validate_url($url, &$error) {
    $error = '';
    if (!is_string($url) || $url === '' || strlen($url) > 4096
        || preg_match('/[\x00-\x20\x7f-\xff\\\\]/', $url)
        || preg_match('/%(?:0[0-9a-f]|1[0-9a-f]|7f|5c)/i', $url)) {
        $error = '图片链接包含空白、控制字符、反斜线或非 ASCII 字符。';
        return false;
    }
    $parts = @parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
        || isset($parts['user']) || isset($parts['pass'])) {
        $error = '请输入不含用户名或密码的完整 HTTP/HTTPS 图片链接。';
        return false;
    }
    $scheme = strtolower($parts['scheme']);
    $host = strtolower($parts['host']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        $error = '图片链接只允许 HTTP 或 HTTPS。';
        return false;
    }
    $port = $scheme === 'https' ? 443 : 80;
    if (isset($parts['port']) && $parts['port'] !== $port) {
        $error = '图片链接只能使用协议默认端口（HTTP 80、HTTPS 443）。';
        return false;
    }
    // 字母顶级域名同时排除十进制、八进制、十六进制及缩写 IP 表示。
    if (strlen($host) > 253 || !preg_match('/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/D', $host)
        || filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $error = '图片链接必须使用完整公网域名，不允许 IP 地址或本地主机名。';
        return false;
    }
    $path = isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';
    $normalized = $scheme . '://' . $host . $path;
    if (isset($parts['query'])) {
        $normalized .= '?' . $parts['query'];
    }
    return ['url' => $normalized, 'host' => $host, 'scheme' => $scheme, 'port' => $port];
}

/** 二进制 CIDR 比较，避免 32 位 PHP 的整数溢出。 */
function remote_image_ip_in_cidr($ip, $network, $prefix) {
    $address = @inet_pton($ip);
    $base = @inet_pton($network);
    if ($address === false || $base === false || strlen($address) !== strlen($base)) {
        return false;
    }
    $bytes = intdiv($prefix, 8);
    $bits = $prefix % 8;
    if (substr($address, 0, $bytes) !== substr($base, 0, $bytes)) {
        return false;
    }
    return $bits === 0 || ((ord($address[$bytes]) ^ ord($base[$bytes])) & (255 << (8 - $bits))) === 0;
}

/** 保守的公网地址判定；IPv6 仅用于审查 AAAA，实际连接只使用 IPv4。 */
function remote_image_is_public_ip($ip) {
    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        $blocked = [
            ['0.0.0.0', 8], ['10.0.0.0', 8], ['100.64.0.0', 10], ['127.0.0.0', 8],
            ['169.254.0.0', 16], ['172.16.0.0', 12], ['192.0.0.0', 24], ['192.0.2.0', 24],
            ['192.88.99.0', 24], ['192.168.0.0', 16], ['198.18.0.0', 15],
            ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4],
        ];
    } else {
        // 仅认可全球单播范围，进一步排除协议、文档、6to4 等特殊用途段。
        if (!remote_image_ip_in_cidr($ip, '2000::', 3)) {
            return false;
        }
        $blocked = [['2001::', 23], ['2001:db8::', 32], ['2002::', 16], ['3fff::', 20]];
    }
    foreach ($blocked as $range) {
        if (remote_image_ip_in_cidr($ip, $range[0], $range[1])) {
            return false;
        }
    }
    return true;
}

/** 独立验证全部 DNS 记录，任何非公网 A/AAAA 均导致整体拒绝。 */
function remote_image_validate_dns_records(array $records, &$error) {
    $error = '';
    $addresses = [];
    foreach ($records as $record) {
        $type = isset($record['type']) ? strtoupper($record['type']) : '';
        if ($type !== 'A' && $type !== 'AAAA') {
            continue;
        }
        $key = $type === 'A' ? 'ip' : 'ipv6';
        $ip = isset($record[$key]) ? $record[$key] : '';
        $family = $type === 'A' ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6;
        if (filter_var($ip, FILTER_VALIDATE_IP, $family) === false || !remote_image_is_public_ip($ip)) {
            $error = '图片域名解析包含非公网或特殊用途地址，已拒绝下载。';
            return false;
        }
        if ($type === 'A') {
            $addresses[] = $ip;
        }
    }
    if (!$addresses) {
        $error = '图片域名没有可用的公网 IPv4 地址。';
        return false;
    }
    return array_values(array_unique($addresses));
}

/** 校验本地临时文件并保存；只供可信服务端调用，不接受用户提交的本地路径。 */
function remote_image_save_file($file, &$error) {
    $error = '';
    clearstatcache(true, $file);
    $size = @filesize($file);
    if ($size === false || $size < 1 || $size > 8 * 1024 * 1024) {
        $error = '图片为空或超过 8 MiB 大小限制。';
        return false;
    }
    if (!function_exists('getimagesize')) {
        $error = '服务器缺少图片检测功能，无法安全保存图片。';
        return false;
    }
    $info = @getimagesize($file);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!$info || !isset($info['mime'], $extensions[$info['mime']])) {
        $error = '内容不是有效的 JPG、PNG、WebP 或 GIF 图片；不接受 SVG 或 HTML。';
        return false;
    }
    $width = (int)$info[0];
    $height = (int)$info[1];
    if ($width < 1 || $height < 1 || $width > 16000 || $height > 16000 || $width > 40000000 / $height) {
        $error = '图片尺寸过大：单边最多 16000 像素，总像素最多 4000 万。';
        return false;
    }
    if (!is_dir(UPLOAD_DIR) && !@mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        $error = '无法创建图片保存目录。';
        return false;
    }
    try {
        $name = 'remote_' . bin2hex(random_bytes(16)) . '.' . $extensions[$info['mime']];
    } catch (Throwable $e) {
        $error = '服务器无法生成安全的图片文件名。';
        return false;
    }
    $destination = UPLOAD_DIR . '/' . $name;
    $input = @fopen($file, 'rb');
    if ($input === false) {
        $error = '无法读取图片临时文件。';
        return false;
    }
    $output = @fopen($destination, 'xb');
    if ($output === false) {
        fclose($input);
        $error = '无法写入图片保存目录。';
        return false;
    }
    $copied = @stream_copy_to_stream($input, $output, 8 * 1024 * 1024 + 1);
    $flushed = @fflush($output);
    fclose($input);
    fclose($output);
    if ($copied !== $size || !$flushed) {
        @unlink($destination);
        $error = '图片保存失败。';
        return false;
    }
    @chmod($destination, 0644);
    return UPLOAD_REL . '/' . $name;
}

/** 无网络内容保存入口，便于测试和可信服务端复用。 */
function remote_image_save_content($content, &$error) {
    $error = '';
    if (!is_string($content) || strlen($content) < 1 || strlen($content) > 8 * 1024 * 1024) {
        $error = '图片为空或超过 8 MiB 大小限制。';
        return false;
    }
    $stream = @tmpfile();
    if ($stream === false) {
        $error = '无法创建图片临时文件。';
        return false;
    }
    try {
        if (@fwrite($stream, $content) !== strlen($content) || !@fflush($stream)) {
            $error = '图片临时文件写入失败。';
            return false;
        }
        $meta = stream_get_meta_data($stream);
        return remote_image_save_file($meta['uri'], $error);
    } finally {
        fclose($stream);
    }
}

/** 下载远程图片，成功返回站内相对路径，失败返回 false 并设置中文 error。 */
function remote_image_download($url, &$error, $deadline = null) {
    $error = '';
    $started = microtime(true);
    if ($deadline !== null && (!is_numeric($deadline) || !is_finite((float)$deadline))) {
        $error = '图片下载截止时间无效。';
        return false;
    }
    $expires = $deadline === null ? $started + 15 : min($started + 15, (float)$deadline);
    if ($expires <= $started) {
        $error = '图片下载时间预算已用尽。';
        return false;
    }
    $target = remote_image_validate_url($url, $error);
    if ($target === false) {
        return false;
    }
    if (!function_exists('curl_init') || !defined('CURLOPT_RESOLVE')) {
        $error = '服务器未安装或启用所需的 PHP cURL 扩展，无法下载远程图片。';
        return false;
    }
    if (!function_exists('dns_get_record')) {
        $error = '服务器缺少 DNS 查询功能，无法安全下载远程图片。';
        return false;
    }
    // PHP 的同步 DNS 查询由系统解析器控制；查询后再次检查共享截止时间。
    $records = @dns_get_record($target['host'], DNS_A | DNS_AAAA);
    if ($records === false) {
        $error = '图片域名 DNS 查询失败。';
        return false;
    }
    $addresses = remote_image_validate_dns_records($records, $error);
    if ($addresses === false) {
        return false;
    }
    $remaining = (int)floor(($expires - microtime(true)) * 1000);
    if ($remaining < 1) {
        $error = '图片下载时间预算已用尽。';
        return false;
    }
    $stream = @tmpfile();
    if ($stream === false) {
        $error = '无法创建图片临时文件。';
        return false;
    }
    $curl = null;
    try {
        $curl = curl_init();
        if ($curl === false) {
            $error = '无法初始化图片下载。';
            return false;
        }
        $bytes = 0;
        $headerBytes = 0;
        $status = 0;
        $transferError = '';
        $options = [
            CURLOPT_URL => $target['url'],
            CURLOPT_RESOLVE => [$target['host'] . ':' . $target['port'] . ':' . $addresses[0]],
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT_MS => min(5000, $remaining),
            CURLOPT_TIMEOUT_MS => $remaining,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_USERAGENT => 'ShopRemoteImage/1.0',
            CURLOPT_HTTPHEADER => ['Accept: image/jpeg, image/png, image/webp, image/gif', 'Accept-Encoding: identity'],
            CURLOPT_HEADERFUNCTION => function ($handle, $line) use (&$headerBytes, &$status, &$transferError, $expires) {
                $length = strlen($line);
                $headerBytes += $length;
                if (microtime(true) >= $expires || $headerBytes > 65536) {
                    $transferError = '下载超时或响应头过大。';
                    return 0;
                }
                if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', $line, $match)) {
                    $status = (int)$match[1];
                    if ($status >= 300 && $status < 400) {
                        $transferError = '图片链接发生重定向，请使用图片的最终直达链接。';
                        return 0;
                    }
                }
                if (preg_match('/^Content-Length:\s*(\d+)/i', $line, $match) && (float)$match[1] > 8 * 1024 * 1024) {
                    $transferError = '图片超过 8 MiB 大小限制。';
                    return 0;
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => function ($handle, $chunk) use ($stream, &$bytes, &$transferError, $expires) {
                $length = strlen($chunk);
                if (microtime(true) >= $expires) {
                    $transferError = '图片下载时间预算已用尽。';
                    return 0;
                }
                if ($length > 8 * 1024 * 1024 - $bytes) {
                    $transferError = '图片超过 8 MiB 大小限制。';
                    return 0;
                }
                $written = @fwrite($stream, $chunk);
                if ($written !== $length) {
                    $transferError = '图片临时文件写入失败。';
                    return 0;
                }
                $bytes += $length;
                return $length;
            },
        ];
        if (!curl_setopt_array($curl, $options)) {
            $error = '服务器无法启用安全图片下载选项。';
            return false;
        }
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($transferError !== '') {
            $error = $transferError;
            return false;
        }
        if ($status >= 300 && $status < 400) {
            $error = '图片链接发生重定向，请使用图片的最终直达链接。';
            return false;
        }
        if ($ok === false) {
            $error = '图片下载失败（网络、TLS 验证或超时错误，代码 ' . curl_errno($curl) . '）。';
            return false;
        }
        if ($status !== 200) {
            $error = '图片服务器返回 HTTP ' . $status . '，请检查直达链接。';
            return false;
        }
        if (microtime(true) >= $expires) {
            $error = '图片下载时间预算已用尽。';
            return false;
        }
        if (!@fflush($stream)) {
            $error = '图片临时文件写入失败。';
            return false;
        }
        $meta = stream_get_meta_data($stream);
        return remote_image_save_file($meta['uri'], $error);
    } catch (Throwable $e) {
        $error = '远程图片处理失败，请检查服务器扩展与存储权限。';
        return false;
    } finally {
        if ($curl !== null && $curl !== false) {
            curl_close($curl);
        }
        fclose($stream);
    }
}
