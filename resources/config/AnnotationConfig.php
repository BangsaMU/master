<?php

/*
|--------------------------------------------------------------------------
| Default "launch token" untuk viewer annotation
|--------------------------------------------------------------------------
| File ini di-merge ke config('AnnotationConfig') milik app. Sengaja HANYA
| berisi key 'launch' supaya key 'main' (URL, VALIDATE_SESSION, dst) milik app
| tidak tertimpa. Semua opsi opt-in: default = perilaku lama (token md5(email)).
*/

return [
    'launch' => [
        /* legacy = link viewer memakai md5(email) seperti sebelumnya, launch = link lewat route annotation.open + token terenkripsi */
        'TOKEN_MODE' => env('ANNOTATION_TOKEN_MODE', 'legacy'),
        /* hanya berlaku di mode launch: masih terima token lama (api_token / md5(email)) selama masa transisi */
        'ACCEPT_LEGACY_TOKEN' => env('ANNOTATION_ACCEPT_LEGACY_TOKEN', true),
        /* umur token launch dalam menit */
        'TOKEN_TTL' => env('ANNOTATION_TOKEN_TTL', 120),
        /* cek session id di token masih aktif di session store app (butuh SESSION_DRIVER server-side: redis, file, database) */
        'CHECK_SESSION' => env('ANNOTATION_TOKEN_CHECK_SESSION', true),
        /* getSignature / getParaf: URL gambar (berisi token launch) disimpan permanen di anotasi, jadi token asli
           tetap diterima setelah kedaluwarsa / session berakhir. false = cek sama ketatnya dengan getcurrentuser */
        'IMAGE_TOKEN_PERMANENT' => env('ANNOTATION_IMAGE_TOKEN_PERMANENT', true),
        /* host tambahan (dipisah koma) yang boleh dipakai di file_url / callback, selain host app sendiri */
        'ALLOWED_HOSTS' => env('ANNOTATION_ALLOWED_HOSTS', ''),
    ],
];
