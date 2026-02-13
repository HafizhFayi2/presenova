<?php
return [
    'enabled' => true,
    'vapid_public_key' => 'BCG0xt0-sNj6YXeTbpU_tw4tYOkIm4kZR1O-k8_UuX7cx-1OoK1gwdu9-kdqL_qVbB_974FJ34GiQwJmJbRQOXI',
    'vapid_private_key' => 'mKZzKn4IZBuryx84JpMxU0zaeTYfS89BizAjVfDszcg',
    'subject' => 'mailto:admin@presenova.local',
    'node_bin' => 'node',
    'send_script' => __DIR__ . '/../scripts/webpush/send.js'
];
