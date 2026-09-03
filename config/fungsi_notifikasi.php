<?php
function kirimNotifikasiBaruPetugas($onesignal_id, $judul, $pesan) {
    $app_id = "ab9bc8bb-e8bc-41e2-bacb-0664b10654ae";
    $rest_api_key = "os_v2_app_von4ro7ixra6fowlazslcbsuv3thxgghjyruxh5nke3o7zx5iunqmqsdhcvnj2azessuby4ealytqd5zftv6hzelxcmbhfv6ouy3e6i";

    $fields = array(
    'app_id' => $app_id,
    'include_player_ids' => array($onesignal_id), // <-- GANTI MENJADI INI
    'headings' => array("en" => $judul),
    'contents' => array("en" => $pesan)
);

    $fields_string = json_encode($fields);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8',
                                               'Authorization: Basic ' . $rest_api_key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Log hasil untuk debugging
    if (curl_errno($ch)) {
        error_log('OneSignal Error: ' . curl_error($ch));
    } else {
        error_log('OneSignal Response (Code ' . $http_code . '): ' . $response);
    }
    
    curl_close($ch);
    return $response;
}
?>