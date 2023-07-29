<?php

function getIpLocation($ip) {
    $url = "http://ipinfo.io/{$ip}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function checkProxy($proxy) {
    $httpResult = checkProxyWithProtocol($proxy, 'http://www.google.com/');
    $httpsResult = checkProxyWithProtocol($proxy, 'https://www.google.com/');

    if ($httpResult['status'] && $httpsResult['status']) {
        if ($httpResult['time'] >= $httpsResult['time']) {
            $result = $httpResult;
            $result['protocol'] = 'HTTP';
        } else {
            $result = $httpsResult;
            $result['protocol'] = 'HTTPS';
        }
    } elseif ($httpResult['status']) {
        $result = $httpResult;
        $result['protocol'] = 'HTTP';
    } elseif ($httpsResult['status']) {
        $result = $httpsResult;
        $result['protocol'] = 'HTTPS';
    } else {
        $result = [
            'status' => false,
            'time' => null,
            'ip' => null,
            'location' => null,
            'protocol' => null
        ];
    }
    return $result;
}

function checkProxyWithProtocol($proxy, $testUrl) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000; // Convert to ms
    $ip = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    curl_close($ch);

    if (!$error) {
        $location = getIpLocation($ip);
        return ['status' => true, 'time' => $totalTime, 'ip' => $ip, 'location' => $location];
    }
    return ['status' => false, 'time' => null, 'ip' => null, 'location' => null];
}

function getproxy(){
    $totalproxy = 0;
    $ch = curl_init();
    $url = "https://api.proxyscrape.com/v2/?request=getproxies&protocol=http&timeout=10000&country=all&ssl=all&anonymity=all&simplified=true";
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        echo "Error: " . curl_error($ch);
        exit;
    } curl_close($ch);
    if (!empty($response)) {
        $proxy_list = explode("\n", trim($response));
        foreach ($proxy_list as $proxy) {
            file_put_contents('proxy.txt', $proxy , FILE_APPEND);
            $totalproxy++;
        } 
        echo "$totalproxy proxies found\n";
    } else {
        echo "No proxies found in the response.\n";
    }
}

function main() {
    getproxy();
    $getlist = "proxy.txt";
    $proxies = preg_split(
        '/\n|\r\n?/',
        trim(file_get_contents($getlist))
    );
    $workingProxies = [];
    $totalPingTime = 0;
    foreach ($proxies as $proxy) {
        $result = checkProxy($proxy);
        if ($result['status']) {
            $workingProxies[] = $proxy;
            $totalPingTime += $result['time'];
            echo "Proxy $proxy is working (Protocol: {$result['protocol']}, Location: {$result['location']['city']}, {$result['location']['region']}, {$result['location']['country']}, Ping Time: {$result['time']} ms)\n";
            file_put_contents('proxy-valid.txt', "$proxy - Protocol: {$result['protocol']}: {$result['location']['region']} - {$result['location']['city']} - {$result['location']['country']} - Ping Time: {$result['time']} ms" . PHP_EOL, FILE_APPEND);
        } else {
            echo "Proxy $proxy is not working\n";
        }
    }
    echo "Total Working Proxies: " . count($workingProxies) . "\n";
    echo "Average Ping Time: " . ($totalPingTime / count($workingProxies)) . " ms\n";
}

main();
?>
