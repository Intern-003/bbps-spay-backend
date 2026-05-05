<?php
$ch = curl_init('https://ifconfig.me/ip');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$out = curl_exec($ch);
curl_close($ch);

echo "Your server's public IP is: " . $out;
