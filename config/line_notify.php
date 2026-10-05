<?php
/**
 * config/line_notify.php
 * ฟังก์ชันสำหรับส่งข้อความเข้า LINE
 */
function sendLineNotify($message) {
    $token = 'YOUR_LINE_NOTIFY_TOKEN_HERE'; // ใส่ Token ที่ได้มาตรงนี้
    
    $queryData = ['message' => $message];
    $queryData = http_build_query($queryData, '', '&');
    
    $headerOptions = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                         "Authorization: Bearer $token\r\n" .
                         "Content-Length: " . strlen($queryData) . "\r\n",
            'content' => $queryData
        ]
    ];
    
    $context = stream_context_create($headerOptions);
    return file_get_contents('https://notify-api.line.me/api/notify', FALSE, $context);
}