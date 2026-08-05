
<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$user = "chiledao";
$pass = "pass.123.123";

$host = "https://localhost:2083";

$params = [
    'email'    => 'testing',
    'domain'   => 'chiledao.cl',
    'password' => 'PasswordSegura123!',
    'quota'    => 500
];

$url = $host . "/execute/Email/add_pop?" . http_build_query($params);

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => "$user:$pass",
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$result = curl_exec($ch);

echo "<pre>";

echo "HTTP: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
echo "ERROR CURL: " . curl_error($ch) . PHP_EOL;
echo PHP_EOL;

$json = json_decode($result, true);

print_r($json);

echo "</pre>";

curl_close($ch);