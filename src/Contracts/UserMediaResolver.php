<?php

namespace Bangsamu\Master\Contracts;

/**
 * Pembaca gambar user dari Master Data (signature, paraf, foto profil di bucket app03) untuk api/getSignature dan
 * api/getParaf. Nilai user_details berawalan "app03:" (rujukan Master Data, mis. "app03:signature") diselesaikan lewat
 * implementasi ini; implementasinya disediakan paket bangsamu/sso (cache lokal + API Master Data).
 */
interface UserMediaResolver
{
    /**
     * Path file PNG lokal (cache) gambar $type milik $email; null bila Master Data menyatakan gambar tidak ada.
     * Melempar exception bila belum ada cache dan Master Data tidak terjangkau.
     */
    public function localPath(string $type, string $email): ?string;
}
