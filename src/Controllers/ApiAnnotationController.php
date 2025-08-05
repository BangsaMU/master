<?php

namespace Bangsamu\Master\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Image;
use Mpdf\Mpdf;
use setasign\Fpdi\Tcpdf\Fpdi;
use App\Http\Controllers\Controller;
use App\Models\UserDetail;
use App\Models\User;

use Illuminate\Support\Facades\Auth;
use Bangsamu\LibraryClay\Controllers\LibraryClayController;

//jika punya routing skema dari spb enl contohnya
use App\Models\Gallery;
use App\Models\Routing;
use App\Models\Requisition;


class ApiAnnotationController extends Controller
{
    public function viewer(Request $request)
    {
        return view('viewer');
    }

    public function getFileData(Request $request)
    {
        try {
            $file = DB::table('ma_file_manager')
                ->select('id', 'file_url', 'file_name')
                ->where('file_url', $request->file_url)
                ->first();

            $file_url = $request->file_url;
            $file_url_arr = explode('/', $file_url);
            $file_name = str_replace('.pdf', '', $file_url_arr[count($file_url_arr) - 1]);

            if (empty($file)) {


                $file_new = DB::table('ma_file_manager')->insert([
                    'file_url' => $file_url,
                    'file_name' => $file_name
                ]);

                $data = [
                    'success' => true,
                    'message' => 'Success added data!',
                    'data' => $file_new
                ];
            } else {
                $data = [
                    'success' => true,
                    'message' => 'Success!',
                    'data' => $file
                ];
            }

            $parsedUrl = parse_url($file_url);
            $host_url = $parsedUrl['host'] ?? '';
            $sub_folder = str_replace('.', '-', $host_url);

            $path = storage_path('app/public/' . $sub_folder);

            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            $data['data']->sub_folder = $sub_folder;

            $filePath = $sub_folder . '/' . $file_name . '-' . $file->id . '.pdf';
            if (!Storage::disk('public')->exists($filePath)) {
                $response = Http::get($file_url);

                if ($response->successful()) {
                    Storage::disk('public')->put($filePath, $response->body());
                    chmod(Storage::disk('public')->path($filePath), 0777);
                }
            }
        } catch (\Throwable $th) {
            $data = [
                'success' => false,
                'message' => 'Theres something an error!'
            ];
        }
        return response()->json($data);
    }


    public function wrapText($fontSize, $angle, $fontPath, $text, $maxWidth)
    {
        $words = explode(" ", $text);
        $lines = [];
        $currentLine = "";

        foreach ($words as $word) {
            $testLine = $currentLine . " " . $word;
            $bbox = imagettfbbox($fontSize, $angle, $fontPath, trim($testLine));
            $textWidth = $bbox[2] - $bbox[0];

            if ($textWidth > $maxWidth) {
                $lines[] = trim($currentLine);
                $currentLine = $word;
            } else {
                $currentLine = $testLine;
            }
        }
        $lines[] = trim($currentLine);
        return $lines;
    }

    public function loadImageOLD($imagePath)
    {
        if (!file_exists($imagePath)) {
            throw new \Exception("File tidak ditemukan: $imagePath");
        }

        $imageType = exif_imagetype($imagePath); // Deteksi tipe file

        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($imagePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($imagePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($imagePath);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($imagePath);
            default:
                throw new \Exception("Format gambar tidak didukung: $imagePath");
        }
    }
    function loadImage($imagePath)
    {
        if (!file_exists($imagePath)) {
            throw new \Exception("File tidak ditemukan: $imagePath");
        }

        $imageInfo = getimagesize($imagePath);

        if ($imageInfo === false) {
            throw new \Exception("Tidak dapat membaca informasi gambar.");
        }

        $mime = $imageInfo['mime'];

        switch ($mime) {
            case 'image/jpeg':
                // return imagecreatefromjpeg($imagePath);
                $imagePathPNG = self::convertJPGtoPNGWithTransparency($imagePath);
                return imagecreatefrompng($imagePathPNG);
            case 'image/png':
                return imagecreatefrompng($imagePath);
            case 'image/gif':
                return imagecreatefromgif($imagePath);
            case 'image/webp':
                return imagecreatefromwebp($imagePath);
            default:
                throw new \Exception("Format gambar tidak didukung: $mime");
        }
    }

    function convertJPGtoPNGWithTransparency($inputFile, $outputFile = null)
    {
        if (!file_exists($inputFile)) {
            throw new \Exception("File tidak ditemukan: $inputFile");
        }
        if (empty($pgnfFile)) {
            $pathinfo = pathinfo($inputFile);
            $outputFile = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.png';
            return $outputFile;
            // dd( $outputFile ,pathinfo($inputFile.'.jpg'),$inputFile);
        }
        // Load gambar JPG
        $image = imagecreatefromjpeg($inputFile);
        if (!$image) {
            throw new \Exception("Gagal memuat gambar JPG");
        }

        // Dapatkan ukuran gambar
        $width = imagesx($image);
        $height = imagesy($image);

        // Buat gambar PNG dengan transparansi
        $transparentImage = imagecreatetruecolor($width, $height);
        imagesavealpha($transparentImage, true);
        $transparentColor = imagecolorallocatealpha($transparentImage, 255, 255, 255, 127);
        imagefill($transparentImage, 0, 0, $transparentColor);

        // Salin gambar dari JPG ke PNG dengan transparansi
        imagecopy($transparentImage, $image, 0, 0, 0, 0, $width, $height);

        // Konversi warna putih menjadi transparan
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $pixelColor = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $pixelColor);

                // Jika warna mendekati putih, buat transparan
                if ($colors['red'] > 200 && $colors['green'] > 200 && $colors['blue'] > 200) {
                    imagesetpixel($transparentImage, $x, $y, $transparentColor);
                }
            }
        }

        // Simpan hasil ke file PNG
        imagepng($transparentImage, $outputFile);

        // Hapus gambar dari memori
        imagedestroy($image);
        imagedestroy($transparentImage);

        return $outputFile;
    }

    function getTokenIdOrEmail($token=null) {
        $user = user::where('api_token', $token)->first() ?? null;
        if ($user === null) {
            $user = User::whereRaw('MD5(email) = ?', [$token])->first() ?? abort(401);;
        }
        return $user;
    }

    public function getSignatureWithTimeStamp($signature_path, $request)
    {
        $token = $request->token;
        $user = $this->getTokenIdOrEmail($token);
        $name = $user->name;

        // Cek apakah file tanda tangan ada
        if (!file_exists($signature_path)) {
            abort(403, 'Signature file not found.');
        }

        // Ambil waktu saat ini
        $currentTime = $request->currentTime ?? date('Y-m-d H:i:s');
        $strtotime = strtotime(base64_decode($currentTime));
        $dateFormat = date('d M Y H:i:s', $strtotime);

        // Simpan gambar dengan timestamp
        $outputPath = '/tmp/ttd_with_timestamp' . basename($signature_path) . $dateFormat . '.png';
        if (file_exists($outputPath)) {
            return $outputPath;
        }

        try {
            // Muat gambar tanda tangan asli
            $signatureImage = self::loadImage($signature_path);
        } catch (\Exception $e) {
            die("Error: " . $e->getMessage());
        }

        // Dapatkan dimensi gambar asli
        $originalWidth = imagesx($signatureImage);
        $originalHeight = imagesy($signatureImage);

        // Buat canvas baru dengan ukuran yang sama
        $canvas = imagecreatetruecolor($originalWidth, $originalHeight);

        // Set background transparan
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagealphablending($canvas, true);

        // Copy gambar signature ke canvas (posisi paling atas)
        imagecopy($canvas, $signatureImage, 0, 0, 0, 0, $originalWidth, $originalHeight);

        // Konversi tanda tangan ke warna yang diinginkan
        $annotation_sign = LibraryClayController::getSettingByCategory('annotation_sign');
        $hex = $annotation_sign['color'] ?? '#000000';
        list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
        imagefilter($canvas, IMG_FILTER_COLORIZE, $r, $g, $b);

        // Tentukan warna teks (hitam)
        $textColor = imagecolorallocate($canvas, 0, 0, 0);

        // Tentukan ukuran font berdasarkan lebar gambar
        $fontSize = 5 / 100 * $originalWidth;
        $fontSize2 = 7 / 100 * $originalWidth;

        // Path font
        $fontPath = storage_path('/fonts/'.config('AnnotationConfig.main.font','arial.ttf'));

        // Posisi untuk timestamp (di bagian bawah)
        $timestampX = 10;
        $timestampY = $originalHeight - 60; // Jarak dari bawah untuk timestamp

        // Tambahkan timestamp ke canvas
        imagettftext($canvas, $fontSize, 0, $timestampX, $timestampY, $textColor, $fontPath, $dateFormat);

        // Posisi untuk nama (di bawah timestamp)
        $nameY = $originalHeight - 30; // Lebih ke bawah dari timestamp
        $maxWidth = $originalWidth - 20;
        $lines = self::wrapText($fontSize2, 0, $fontPath, $name, $maxWidth);

        // Tulis nama per baris
        foreach ($lines as $i => $line) {
            $lineY = $nameY + ($i * ($fontSize2 + 5));
            // Pastikan teks tidak keluar dari canvas
            if ($lineY < $originalHeight - 10) {
                imagettftext($canvas, $fontSize2, 0, 10, $lineY, $textColor, $fontPath, $line);
            }
        }

        // Simpan hasil ke file
        imagepng($canvas, $outputPath);

        // Hapus resource gambar dari memori
        imagedestroy($signatureImage);
        imagedestroy($canvas);

        return $outputPath;
    }
    public function getSignatureWithTimeStampKanan($signature_path, $request)
    {
        $token = $request->token;
        $user = $this->getTokenIdOrEmail($token);
        $name = $user->name;

        // Cek apakah file tanda tangan ada
        if (!file_exists($signature_path)) {
            abort(403, 'Signature file not found.');
        }

        // Ambil waktu saat ini
        $currentTime = $request->currentTime ?? date('Y-m-d H:i:s');
        $strtotime = strtotime(base64_decode($currentTime));
        $dateFormat = date('d M Y H:i:s', $strtotime);

        // Simpan gambar dengan timestamp
        $outputPath = '/tmp/ttd_with_timestamp' . basename($signature_path) . $dateFormat . '.png';
        if (file_exists($outputPath)) {
            return $outputPath;
        }

        try {
            // Muat gambar tanda tangan asli
            $signatureImage = self::loadImage($signature_path);
        } catch (\Exception $e) {
            die("Error: " . $e->getMessage());
        }

        // Dapatkan dimensi gambar asli
        $originalWidth = imagesx($signatureImage);
        $originalHeight = imagesy($signatureImage);

        // Buat canvas baru dengan ukuran yang sama
        $canvas = imagecreatetruecolor($originalWidth, $originalHeight);

        // Set background transparan
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagealphablending($canvas, true);

        // Copy gambar signature ke canvas (posisi paling atas)
        imagecopy($canvas, $signatureImage, 0, 0, 0, 0, $originalWidth, $originalHeight);

        // Konversi tanda tangan ke warna yang diinginkan
        $annotation_sign = LibraryClayController::getSettingByCategory('annotation_sign');
        $hex = $annotation_sign['color'] ?? '#000000';
        list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
        imagefilter($canvas, IMG_FILTER_COLORIZE, $r, $g, $b);

        // Tentukan warna teks (hitam)
        $textColor = imagecolorallocate($canvas, 0, 0, 0);

        // Tentukan ukuran font berdasarkan lebar gambar
        $fontSize = 4 / 100 * $originalWidth;
        $fontSize2 = 5 / 100 * $originalWidth;

        // Path font
        $fontPath = storage_path('/fonts/'.config('AnnotationConfig.main.font','arial.ttf'));

        // Posisi untuk timestamp dan nama (di sebelah kanan)
        $rightSectionX = $originalWidth;// / 2; // Mulai dari tengah canvas
        $maxTextWidth = $originalWidth;// / 2 - 20; // Maksimal setengah lebar canvas minus margin

        // Posisi timestamp (di bagian atas sebelah kanan)
        $timestampY = 30; // Jarak dari atas

        // Wrap text untuk timestamp jika terlalu panjang
        $timestampLines = self::wrapText($fontSize, 0, $fontPath, $dateFormat, $maxTextWidth);

        // Tulis timestamp per baris
        foreach ($timestampLines as $i => $line) {
            $lineY = $timestampY + ($i * ($fontSize + 5));
            imagettftext($canvas, $fontSize, 0, $rightSectionX, $lineY, $textColor, $fontPath, $line);
        }

        // Posisi untuk nama (di bawah timestamp)
        $nameY = $timestampY + (count($timestampLines) * ($fontSize + 5)) + 15; // 15px gap setelah timestamp
        $nameLines = self::wrapText($fontSize2, 0, $fontPath, $name, $maxTextWidth);

        // Tulis nama per baris
        foreach ($nameLines as $i => $line) {
            $lineY = $nameY + ($i * ($fontSize2 + 5));
            // Pastikan teks tidak keluar dari canvas
            if ($lineY < $originalHeight - 10) {
                imagettftext($canvas, $fontSize2, 0, $rightSectionX, $lineY, $textColor, $fontPath, $line);
            }
        }

        // Simpan hasil ke file
        imagepng($canvas, $outputPath);

        // Hapus resource gambar dari memori
        imagedestroy($signatureImage);
        imagedestroy($canvas);

        return $outputPath;
    }

    public function getSignatureWithTimeStampOLD($signature_path, $request)
    {
        // dd(pathinfo($signature_path));
        $token = $request->token;
        $user = $this->getTokenIdOrEmail($token);
        $name = $user->name;
        // dd( $token,$name);
        // Cek apakah file tanda tangan ada
        if (!file_exists($signature_path)) {
            // Jika file tidak ditemukan
            abort(403, 'Signature file not found.');
        }

        // $signature_path = storage_path($signature->field_value);
        // Lokasi gambar tanda tangan
        $imagePath = $signature_path;

        // dd(pathinfo($signature_path));
        // Ambil waktu saat ini
        $currentTime = $request->currentTime ?? date('Y-m-d H:i:s');
        $strtotime = strtotime(base64_decode($currentTime));
        $dateFormat = date('d M Y H:i:s', $strtotime);
        // Simpan gambar dengan timestamp
        $outputPath = '/tmp/ttd_with_timestamp' . basename($signature_path) . $dateFormat . '.png';
        if (file_exists($outputPath)) {
            return $outputPath;
        }

        // Muat gambar tanda tangan dengan transparansi
        // $image = imagecreatefrompng($imagePath);
        try {
            $image = self::loadImage($imagePath); // Memuat gambar dengan deteksi otomatis
        } catch (\Exception $e) {
            die("Error: " . $e->getMessage());
        }

        // Konversi tanda tangan ke warna biru
        $annotation_sign = LibraryClayController::getSettingByCategory('annotation_sign');
        $hex = $annotation_sign['color'] ?? '#000000'; // fallback kalau null
        list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
        imagefilter($image, IMG_FILTER_COLORIZE, $r, $g, $b);

        // Aktifkan mode alpha untuk transparansi
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Tentukan warna teks (hitam)
        $textColor = imagecolorallocate($image, 0, 0, 0);
        // $textColor = imagecolorallocate($image, 255, 255, 255);

        // Tentukan font (gunakan font default atau path ke file font .ttf)
        $fontSize = 5 / 100 * (imagesx($image)); // Font default GD (ukuran kecil)
        $fontSize2 = 7 / 100 * (imagesx($image)); // Font default GD (ukuran kecil)
        // Jika ingin menggunakan font custom, contoh:
        $fontPath = storage_path('/fonts/'.config('AnnotationConfig.main.font','arial.ttf'));

        // Tentukan posisi teks (x, y)
        $x = 10; // Jarak dari kiri
        $y = 10 + $fontSize; // Jarak dari bawah
        $y2 = imagesy($image) - 100; // Jarak dari bawah


        // Tambahkan teks timestamp ke gambar
        imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $dateFormat);
        // imagettftext($image, $fontSize2, 0, $x, $y2, $textColor, $fontPath, $name);
        // imagestring($image, $fontSize, $x, $y, $currentTime, $textColor);

        // Hitung posisi Y awal
        $yStart = imagesy($image) - 100;
        $maxWidth = imagesx($image) - 20;
        $lines = self::wrapText($fontSize2, 0, $fontPath, $name, $maxWidth);

        // Tulis teks per baris
        foreach ($lines as $i => $line) {
            imagettftext($image, $fontSize2, 0, 10, $yStart + ($i * ($fontSize2 + 5)), $textColor, $fontPath, $line);
        }

        imagepng($image, $outputPath);

        // return response()->file($outputPath, [
        //     'Content-Type' => 'image/png'
        // ]);

        // Tampilkan gambar ke browser
        // imagepng($image);
        // Hapus resource gambar dari memori
        imagedestroy($image);
        return $outputPath;
        // echo "Gambar tanda tangan dengan timestamp telah disimpan di: $outputPath";
    }

public function getParafWithTimeStampKanan($paraf_path, $request)
{
    $token = $request->token;
    $user = $this->getTokenIdOrEmail($token);
    $name = $user->name;

    // Cek apakah file tanda tangan ada
    if (!file_exists($paraf_path)) {
        abort(403, 'Paraf file not found.');
    }

    // Ambil waktu saat ini
    $currentTime = $request->currentTime ?? date('Y-m-d H:i:s');
    $strtotime = strtotime(base64_decode($currentTime));
    $dateFormat = date('d M Y H:i:s', $strtotime);

    // Simpan gambar dengan timestamp
    $outputPath = '/tmp/ttd_with_timestamp' . basename($paraf_path) . $dateFormat . '.png';

    if (file_exists($outputPath)) {
        return $outputPath;
    }

    try {
        // Muat gambar paraf asli
        $parafImage = self::loadImage($paraf_path);
    } catch (\Exception $e) {
        die("Error: " . $e->getMessage());
    }

    // Dapatkan dimensi gambar asli
    $originalWidth = imagesx($parafImage);
    $originalHeight = imagesy($parafImage);

    // Buat canvas baru dengan lebar diperluas untuk menampung teks
    $canvasWidth = $originalWidth * 2; // Dobel lebar untuk memberikan ruang teks
    $canvasHeight = $originalHeight;
    $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);

    // Set background transparan
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $transparent);
    imagealphablending($canvas, true);

    // Copy gambar paraf ke canvas (posisi kiri)
    imagecopy($canvas, $parafImage, 0, 0, 0, 0, $originalWidth, $originalHeight);

    // Konversi paraf ke warna yang diinginkan
    $annotation_sign = LibraryClayController::getSettingByCategory('annotation_sign');
    $hex = $annotation_sign['color'] ?? '#000000';
    list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
    imagefilter($canvas, IMG_FILTER_COLORIZE, $r, $g, $b);

    // Tentukan warna teks (hitam)
    $textColor = imagecolorallocate($canvas, 0, 0, 0);

    // Tentukan ukuran font - perbaiki perhitungan
    $fontSize = max(12, ($originalWidth * 6) / 100); // Minimal 12px, lebih besar
    $fontSize2 = max(14, ($originalWidth * 6) / 100); // Minimal 14px, lebih besar

    // Path font - cek apakah font ada
    $fontPath = storage_path('/fonts/'.config('AnnotationConfig.main.font','arial.ttf'));

    // Jika font tidak ada, gunakan font built-in
    $useBuiltInFont = !file_exists($fontPath);

    // Posisi untuk timestamp dan nama (di sebelah kanan paraf)
    $rightSectionX = $originalWidth + 10; // 10px margin dari paraf
    $maxTextWidth = $originalWidth - 20; // Lebar area teks

    // Posisi timestamp - perbaiki agar tidak terpotong
    $timestampY = $fontSize + 10; // Sesuaikan dengan ukuran font + margin

    if ($useBuiltInFont) {
        // Gunakan font built-in GD - ukuran 1-5
        $fontSizeBuiltIn = 5; // Font size maksimal untuk built-in font
        $fontSizeBuiltIn2 = 5; // Font size maksimal untuk built-in font

        // Tulis timestamp
        imagestring($canvas, $fontSizeBuiltIn, $rightSectionX, $timestampY - $fontSize, $dateFormat, $textColor);

        // Tulis nama di bawah timestamp
        $nameY = $timestampY + 25; // Lebih jauh dari timestamp
        imagestring($canvas, $fontSizeBuiltIn2, $rightSectionX, $nameY, $name, $textColor);

    } else {
        // Gunakan TTF font
        // Debug: cek nilai fontSize
        // error_log("Font sizes: $fontSize, $fontSize2");

        // Wrap text untuk timestamp jika terlalu panjang
        $timestampLines = self::wrapText($fontSize, 0, $fontPath, $dateFormat, $maxTextWidth);

        // Tulis timestamp per baris
        foreach ($timestampLines as $i => $line) {
            $lineY = $timestampY + ($i * ($fontSize + 8)); // Tambah spacing antar baris
            imagettftext($canvas, $fontSize, 0, $rightSectionX, $lineY, $textColor, $fontPath, $line);
        }

        // Posisi untuk nama (di bawah timestamp)
        $nameY = $timestampY + (count($timestampLines) * ($fontSize + 8)) + 20; // Lebih besar gap
        $nameLines = self::wrapText($fontSize2, 0, $fontPath, $name, $maxTextWidth);

        // Tulis nama per baris
        foreach ($nameLines as $i => $line) {
            $lineY = $nameY + ($i * ($fontSize2 + 8)); // Tambah spacing antar baris
            // Pastikan teks tidak keluar dari canvas
            if ($lineY < $canvasHeight - 20) { // Beri margin lebih besar dari bawah
                imagettftext($canvas, $fontSize2, 0, $rightSectionX, $lineY, $textColor, $fontPath, $line);
            }
        }
    }

    // Debug: Tambahkan border merah untuk melihat area canvas (opsional)
    // $red = imagecolorallocate($canvas, 255, 0, 0);
    // imagerectangle($canvas, 0, 0, $canvasWidth-1, $canvasHeight-1, $red);

    // Simpan hasil ke file
    imagepng($canvas, $outputPath);

    // Hapus resource gambar dari memori
    imagedestroy($parafImage);
    imagedestroy($canvas);

    return $outputPath;
}


    public function getParafWithTimeStamp($paraf_path, $request)
    {
        // dd(pathinfo($paraf_path));
        $token = $request->token;
        $user = $this->getTokenIdOrEmail($token);
        $name = $user->name;
        // dd( $token,$name);
        // Cek apakah file tanda tangan ada
        if (!file_exists($paraf_path)) {
            // Jika file tidak ditemukan
            abort(403, 'Signature file not found.');
        }

        // $paraf_path = storage_path($signature->field_value);
        // Lokasi gambar tanda tangan
        $imagePath = $paraf_path;

        // dd(pathinfo($paraf_path));
        // Ambil waktu saat ini
        $currentTime = $request->currentTime ?? date('Y-m-d H:i:s');
        $strtotime = strtotime(base64_decode($currentTime));
        $dateFormat = date('d M Y H:i:s', $strtotime);
        // Simpan gambar dengan timestamp
        $outputPath = '/tmp/ttd_with_timestamp' . basename($paraf_path) . $dateFormat . '.png';
        if (file_exists($outputPath)) {
            return $outputPath;
        }

        // Muat gambar paraf dengan transparansi
        // $image = imagecreatefrompng($imagePath);
        try {
            $image = self::loadImage($imagePath); // Memuat gambar dengan deteksi otomatis
        } catch (\Exception $e) {
            die("Error: " . $e->getMessage());
        }
        // Konversi paraf ke warna biru
        $annotation_sign = LibraryClayController::getSettingByCategory('annotation_sign');
        $hex = $annotation_sign['color'] ?? '#000000'; // fallback kalau null
        list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
        imagefilter($image, IMG_FILTER_COLORIZE, $r, $g, $b);
        // imagefilter($image, IMG_FILTER_COLORIZE, 0, 0, 255);

        // Aktifkan mode alpha untuk transparansi
        imagealphablending($image, true);
        imagesavealpha($image, true);

        // Tentukan warna teks (hitam)
        $textColor = imagecolorallocate($image, 0, 0, 0);
        // $textColor = imagecolorallocate($image, 255, 255, 255);

        // Tentukan font (gunakan font default atau path ke file font .ttf)
        $fontSize = 5 / 100 * (imagesx($image)); // Font default GD (ukuran kecil)
        $fontSize2 = 7 / 100 * (imagesx($image)); // Font default GD (ukuran kecil)
        // Jika ingin menggunakan font custom, contoh:
        $fontPath = storage_path('/fonts/'.config('AnnotationConfig.main.font','arial.ttf'));

        // Tentukan posisi teks (x, y)
        $x = 10; // Jarak dari kiri
        $y = 10 + $fontSize; // Jarak dari bawah
        $y2 = imagesy($image) - 100; // Jarak dari bawah


        // Tambahkan teks timestamp ke gambar
        imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $dateFormat);
        // imagettftext($image, $fontSize2, 0, $x, $y2, $textColor, $fontPath, $name);
        // imagestring($image, $fontSize, $x, $y, $currentTime, $textColor);

        // Hitung posisi Y awal
        $yStart = imagesy($image) - 100;
        $maxWidth = imagesx($image) - 20;
        $lines = self::wrapText($fontSize2, 0, $fontPath, $name, $maxWidth);

        // Tulis teks per baris
        foreach ($lines as $i => $line) {
            imagettftext($image, $fontSize2, 0, 10, $yStart + ($i * ($fontSize2 + 5)), $textColor, $fontPath, $line);
        }

        imagepng($image, $outputPath);

        // return response()->file($outputPath, [
        //     'Content-Type' => 'image/png'
        // ]);

        // Tampilkan gambar ke browser
        // imagepng($image);
        // Hapus resource gambar dari memori
        imagedestroy($image);
        return $outputPath;
        // echo "Gambar tanda tangan dengan timestamp telah disimpan di: $outputPath";
    }
    public function getSignatureWithTimeStampKananKekecilan($signature_path, $request)
    {
        $token = $request->token;
        $user = $this->getTokenIdOrEmail($token);
        $name = $user->name;

        if (!file_exists($signature_path)) {
            abort(403, 'Signature file not found.');
        }

        // Ambil waktu saat ini
        $currentTime = $request->currentTime ?? date('Y-m-d H:i:s');
        $strtotime = strtotime(base64_decode($currentTime));
        $dateFormat = date('Y.m.d H:i:s', $strtotime);

        // Lokasi penyimpanan gambar hasil
        $outputPath = '/tmp/ttd_with_timestamp_' . basename($signature_path) . $dateFormat . '.png';
        if (file_exists($outputPath)) {
            return $outputPath;
        }

        // Muat gambar tanda tangan dengan transparansi
        $signature = imagecreatefrompng($signature_path);
        $signatureWidth = imagesx($signature);
        $signatureHeight = imagesy($signature);

        // Tentukan font dan ukuran dinamis
        $fontPath = storage_path('/fonts/arial.ttf');
        $fontSize = $signatureHeight * 0.3; // 30% dari tinggi tanda tangan
        $dateFontSize = $fontSize * 0.8; // Ukuran tanggal lebih kecil

        // Hitung panjang teks
        $textBox = imagettfbbox($fontSize, 0, $fontPath, $name);
        $textWidth = abs($textBox[4] - $textBox[0]); // Lebar teks nama
        $dateBox = imagettfbbox($dateFontSize, 0, $fontPath, $dateFormat);
        $dateWidth = abs($dateBox[4] - $dateBox[0]); // Lebar teks tanggal

        // Tentukan lebar area teks minimal
        $textAreaWidth = max($textWidth, $dateWidth) + 20; // Tambah padding 20px

        // Buat canvas baru yang cukup lebar
        $newWidth = $signatureWidth + $textAreaWidth;
        $newHeight = $signatureHeight;
        $image = imagecreatetruecolor($newWidth, $newHeight);

        // Atur transparansi
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);

        // Salin tanda tangan ke kiri canvas baru
        imagecopy($image, $signature, 0, 0, 0, 0, $signatureWidth, $signatureHeight);

        // Tentukan warna teks
        $textColor = imagecolorallocate($image, 0, 0, 255); // Biru

        // Hitung posisi teks agar sejajar dengan tanda tangan
        $textX = $signatureWidth + 10; // Beri jarak 10px dari tanda tangan
        $textY = ($newHeight / 2) - ($fontSize / 3);

        // Tulis nama
        imagettftext($image, $fontSize, 0, $textX, $textY, $textColor, $fontPath, $name);

        // Tulis tanggal di bawah nama
        imagettftext($image, $dateFontSize, 0, $textX, $textY + ($fontSize * 1.2), $textColor, $fontPath, $dateFormat);

        // Simpan gambar hasil
        imagepng($image, $outputPath);

        // Hapus resource gambar dari memori
        imagedestroy($image);
        imagedestroy($signature);

        return $outputPath;
    }

    //funngsi ambil paraf
    public function getParaf(Request $request)
    {
        $token = $request->token;
        $user = $this->getTokenIdOrEmail($token);
        $user_id = $user->id ?? 0;
        $paraf = UserDetail::select('field_value')->where('field_key', 'paraf')->where('user_id', $user_id)->first();
        // dd($token, $user_id, $paraf->toArray());
        if ($paraf) {

            // dd(Storage::disk('media')->exists($paraf->field_value), Storage::disk('media')->path($paraf->field_value), Storage::disk('media')->url('nama-file.jpg') );
            $paraf_path = Storage::disk('media')->path($paraf->field_value);

            // Cek apakah file paraf ada
            if (Storage::disk('media')->exists($paraf->field_value)) {
                if ($request->currentTime) {
                    if($request->position=='right'){
                        $paraf_path = self::getParafWithTimeStampKanan($paraf_path, $request);
                    }else{
                        $paraf_path = self::getParafWithTimeStamp($paraf_path, $request);
                    }
                }
                return response()->file($paraf_path, [
                    'Content-Type' => 'image/png'
                ]);
            } else {
                // Jika file tidak ditemukan
                abort(403, 'Paraf file not found.');
            }
        } else {
            // Jika paraf tidak ditemukan
            abort(403, 'No paraf found for the user.');
        }


        if ($paraf) {
            // $paraf_url = storage_path($paraf->field_value);
            // dd(storage_path($paraf->field_value),$paraf->toArray());
            $paraf_url = file_get_contents($paraf_path);
            // $file_paraf = storage_path($user->paraf ?? 'no-image.png');
        } else {
            $paraf_url = null;
        }
        return $paraf_url;
    }

    public function getSignature(Request $request)
    {
        $token = $request->token;
        $user = $this->getTokenIdOrEmail($token);
        $user_id = $user->id ?? 0;
        $signature = UserDetail::select('field_value')->where('field_key', 'signature')->where('user_id', $user_id)->first();
        // dd($token, $user_id, $signature->toArray());
        if ($signature) {
            $signature_path = Storage::disk('media')->path($signature->field_value);

            // Cek apakah file tanda tangan ada
            if (Storage::disk('media')->exists($signature->field_value)) {
                if ($request->currentTime) {
                    if($request->position=='right'){
                        $signature_path = self::getSignatureWithTimeStampKanan($signature_path, $request);
                    }else{
                        $signature_path = self::getSignatureWithTimeStamp($signature_path, $request);
                    }
                }
                return response()->file($signature_path, [
                    'Content-Type' => 'image/png'
                ]);
            } else {
                // Jika file tidak ditemukan
                abort(403, 'Signature file not found.');
            }
        } else {
            // Jika signature tidak ditemukan
            abort(403, 'No signature found for the user.');
        }


        if ($signature) {
            // $signature_url = storage_path($signature->field_value);
            // dd(storage_path($signature->field_value),$signature->toArray());
            $signature_url = file_get_contents($paraf_path);
            // $file_signature = storage_path($user->signature ?? 'no-image.png');
        } else {
            $signature_url = null;
        }
        return $signature_url;
    }

    public function setAnnotationId(Request $request)
    {
        $annotation_id = $request->annotation_id;
        $filename = $request->file_name . '.pdf'; //'Route-Slip_20000-SPB-BTN-GA-0010160.pdf'
        $Gallery = Gallery::where('filename', $filename)->update(['annotation_id' => $annotation_id]);
        return $Gallery;
    }

    public function getRequisitionId(Request $request)
    {
        $annotation_id = $request->annotation_id;
        $filename = $request->file_name . '.pdf'; //'Route-Slip_20000-SPB-BTN-GA-0010160.pdf'
        // dd($filename,$request->all());
        $Gallery = Gallery::where('filename', $filename)->first();
        // dd($Gallery,$Gallery->object_id);
        return $Gallery->object_id??null;
    }

    public function getRouting(Request $request)
    {
        $user_id = $request->user_id ?? 0;
        $email = $request->email ?? 0;
        $name = $request->name ?? 0;
        $file_name = $request->file_name ?? 0;
        $file_name_array = explode('Req-Slip_', $file_name);

        $getRequisitionId = self::getRequisitionId($request);
        $annotation_id = $request->annotation_id;

        $Requisition = Requisition::find($getRequisitionId);
        // dd($getRequisitionId,$request->all(),$Requisition->toArray(),$file_name,$file_name_array);
        $version = $Requisition->version??null;
        $requisition_number = @$file_name_array[1];

        $filename = $request->file_name . '.pdf'; //'Route-Slip_20000-SPB-BTN-GA-0010160.pdf'
        $Routing = Routing::where('object_id', $getRequisitionId)
            // ->where('active', 1)
            // ->where('requisition_number', $requisition_number) ganti by requisition.id
            ->where('version', $version)
            ->where('status', 'open')
            ->where('label_email', $email)
            ->first();

        // dd($Routing,$email,@$Gallery->object_id,$filename);
        return $Routing;
    }

    public function getCurrentUser(Request $request)
    {
        $token = $request->token;
        $file_name = $request->file_name;
        $code_number = explode('_', $file_name);
        $permission = false;
        $signature_url = url("api/getSignature?token=" . $token);
        $paraf_url = url("api/getParaf?token=" . $token);
        //cek token id or email
        $user = $this->getTokenIdOrEmail($token);

        $user_id = $user->id ?? 0;
        $email = $user->email ?? 0;
        $name = $user->name ?? 0;

        $request->merge([
            'user_id' => $user_id,
            'email' => $email,
            'name' => $name,
            'file_name' => $file_name
        ]);

        /*ambil mnilai dari env HAVE_ROUTING jika true ada module routing*/
        if (config('app.HAVE_ROUTING')) {
            $getRouting = self::getRouting($request);
            if ($getRouting) {
                $permission = $getRouting->active == 1 ? true : false;
            }else{
                $permission = false;
            }
        }else{
            $getRouting = null;
            $permission = true;
        }

        // dd($getRouting,config('app.HAVE_ROUTING'));



        $value = true;
        // dd($permission,$code_number,$getRouting,$user);
        // $signature = UserDetail::select('field_value')->where('field_key','signature')->where('user_id',$user_id)->first();
        // if($signature){
        // $signature_url = storage_path($signature->field_value);
        // $file_signature = storage_path($user->signature ?? 'no-image.png');
        // }else{
        //     $signature_url = null;
        // }
        // dd($signature,Auth::user());
        if ($value) {
            $data = [
                'success' => true,
                'user' => [
                    'id' => $user_id,
                    'user' => $email,
                    'name' => $name,
                    'permission' => $permission,
                    'routing_id' => @$getRouting->id,
                    // 'signature_url' => public_path() . 'img/signature/signature.png'
                    'signature_url' => $signature_url,
                    'paraf_url' => $paraf_url,
                ],
                // 'user' => [
                //     'id' => 2,
                //     'user' => "demo2@meindo.com",
                //     'name' => "Demo2",
                //     'permission' => '',
                //     'signature_url' => 'https://w7.pngwing.com/pngs/514/114/png-transparent-file-signature-signature-miscellaneous-angle-text-thumbnail.png'
                // ],
            ];
        } else {
            $data = [
                'success' => false,
            ];
        }

        return response()->json($data);
    }

    public function getUserSignatureStatus(Request $request)
    {
        $data = [
            'success' => true,
            'status' => true,
        ];

        return response()->json($data);
    }

    public function getStatusRouting(Request $request)
    {
        $data = [
            'status' => false,
        ];

        return response()->json($data);
    }

    public function getFilePath(Request $request)
    {
        $data = [
            'status' => true,
            // 'file' => public_path() . "/document/pdf/7757-21323-SPB-TCC-IT-000001.pdf"
            // 'file' => "https://pdfobject.com/pdf/sample.pdf"
        ];

        return response()->json($data);
    }

    public function checkMapRoutingTo(Request $request)
    {
        $data = [
            'success' => true,
            'rout' => [
                'file' => public_path() . "/document/pdf/7757-21323-SPB-TCC-IT-000001.pdf",
                // 'file' => "https://pdfobject.com/pdf/sample.pdf",
                'file_name' => "7757-21323-SPB-TCC-IT-000001.pdf",
                'indicate' => "9",
                'person' => "azizi.haq@meindo.com",
            ],

        ];

        return response()->json($data);
    }

    public function routing(Request $request)
    {
        $data = [
            0 => "spb_corporate",
            'type_mod' => "spb_corporate",
        ];

        return response()->json($data);
    }

    public function getPdfFile(Request $request)
    {
        // $file_url = $request->session()->previousUrl();

        // dd($file_url);

        // $path = public_path() . "/document/pdf/7757-21323-SPB-TCC-IT-000001.pdf";
        // DB::table('ma_file_manager')->find($request->file_id)->first();

        $path = "";

        // return response()->json($data);
        echo file_get_contents($path);
    }

    public function getAnnotations(Request $request)
    {
        $countShowAnnotation = DB::table('pdf_annotate')->where('status', 'Show')->count();
        $countHiddenAnnotation = DB::table('pdf_annotate')->where('status', 'Hidden')->count();

        $file_id = $request->file_id;
        $page = $request->page;

        $annotations_db = DB::table('pdf_annotate')
            // ->leftJoin('ts_org_person as tbl2', 'tbl1.person_id', 'tbl2.person_id')
            ->select(
                'annotate_id as id',
                'annotate_type as name',
                'annotate_title as username',
                'annotate_content as content',
                'annotate_rect as rect',
                'annotate_page as page',
                'person_id',
                // 'tbl2.person_name as title',
                'deg',
                'style',
                'status',
                'parent',
            )
            ->where('file_id', $file_id)
            ->where('annotate_page', $page)
            ->get()->toArray();
        // dd($annotations_db);
        $annotations = [];
        $reply_annotation = [];

        foreach ($annotations_db as $annotation) {
            if ($annotation->name == 'Reply') {
                $reply_annotation[] = $annotation;
            } else {
                $annotations[] = $annotation;
            }
        }

        foreach ($annotations as $key => $annotation) {
            if ($annotation->name == 'Comment') {
                foreach ($reply_annotation as $r_ann) {
                    if ($r_ann->parent == $annotation->id) {
                        $annotations[$key]->childern[] = $r_ann;
                    }
                }
            }
        }

        $countAnnotation = count($annotations_db);

        $data = [
            "success" => true,
            "totalAnnot" => $countAnnotation,
            "totalAnnotShow" => $countShowAnnotation,
            "totalAnnotHidden" => $countHiddenAnnotation,
            "annotations" => $annotations,
        ];

        return response()->json($data);
    }

    public function getUserProfile(Request $request)
    {
        $data = [
            "success" => true,
            "user" => [
                "user" => "demo@meindo.com",
                "name" => "Demo",
            ],
        ];

        return response()->json($data);
    }

    // public function getPersonID(Request $request)
    // {
    //     $data = 'azizi.haq@meindo.com';

    //     return response()->json($data);
    // }

    public function getUserLevel(Request $request)
    {
        $data = [
            'success' => true,
            'status' => true,
        ];

        return response()->json($data);
    }

    public function getCommentModificator(Request $request)
    {
        $data = [
            "commentModificator" => "azizi.haq@meindo.com",
            "modificatorName" => "Azizi KH",
        ];

        return response()->json($data);
    }

    public function getUserSignature(Request $request)
    {
        $path = public_path() . '/img/signature/signature.png';
        echo file_get_contents($path);
    }

    public function saveAnnotations(Request $request)
    {

        $r_annotations = $request->annotations;
        $fileId = $request->file_id;
        $routing = $request->routing;
        $username = $request->user;
        $debugStat = 1;
        // dd($r_annotations);
        $newAnnotations = @$r_annotations['new'] ?? [];
        $modifiedAnnotations = @$r_annotations['modified'] ?? [];
        $deletedAnnotations = @$r_annotations['deleted'] ?? [];
        // $hideAnnotations = $_POST['annotations']['hide'];
        // $unhideAnnotations = $_POST['annotations']['unhide'];

        if (!empty($newAnnotations)) {
            $insert = self::insertNewAnnotations($newAnnotations, $fileId, $username, $debugStat);
        }
        if (!empty($modifiedAnnotations)) {
            $update = self::updateModifiedAnnotations($modifiedAnnotations, $fileId, $username, $debugStat);
        }
        if (!empty($deletedAnnotations)) {
            $delete = self::deleteAnnotations($deletedAnnotations, $fileId, $routing, $username, $debugStat);
        }
        // $hide = hideAnnotations($hideAnnotations,$fileId,$routing,$dbLink,$debugStat);
        // $unhide = unhideAnnotations($unhideAnnotations,$fileId,$routing,$dbLink,$debugStat);

        $data = [
            "success" => true,
            "notif" => "Saved",
            "message" => "Annotations Updated & Saved Successfully",
            "annotations" => [
                "new" => @$insert ?? [],
                "modified" => @$update ?? [],
                "deleted" => @$delete ?? [],
                // "hide" => [
                //     "success" => false,
                //     "message" => "no affected annotation",
                // ],
                // "unhide" => [
                //     "success" => false,
                //     "message" => "no affected annotation",
                // ],
            ],
        ];

        return response()->json($data);
    }

    public function insertNewAnnotations($items, $fileId, $username, $debugStat)
    {
        if (!empty($items)) {

            $data = [];
            foreach ($items as $key => $item) {
                $parentAnnt = @$item['parent'];

                $style = json_encode(@$item['style']);

                if (!$style) {
                    $style = '';
                }

                $content = $item['content'];

                if ($item['hide'] == "true") {
                    $displayStat = "Hidden";
                } else {
                    $displayStat = "Show";
                }

                $created_date = date('Y-m-d H:i:s');
                $rect = $item['rect'];
                $rect_real = $item['rect_real'];

                $data[$key] = [
                    'file_id' => $fileId,
                    'annotate_type' => $item['name'],
                    'annotate_title' => $username,
                    'annotate_content' => $content,
                    'annotate_rect' => '[' . @$rect[0] . ',' . @$rect[1] . ',' . @$rect[2] . ',' . @$rect[3] . ']',
                    'annotate_rect_real' => '[' . @$rect_real[0] . ',' . @$rect_real[1] . ',' . @$rect_real[2] . ',' . @$rect_real[3] . ']',
                    'annotate_page' => $item['page'],
                    'person_id' => $username,
                    'parent' => $parentAnnt,
                    'deg' => @$item['deg'] ?? 0,
                    'style' => $style,
                    'status' => $displayStat,
                    'created_date' => $created_date,
                ];

                // $getTypeMod = DB::table('ts_routing')->select('type_mod')->where('rout_id', $routing)->first();

                // $type_mod = $getTypeMod->type_mod;
                // if ($type_mod == "ap_invoices" || $type_mod == "draft_po") {
                //     $data[$key]['annot_type_mod'] = $type_mod;
                //     $data[$key]['annot_rout_id'] = $routing;
                // }
            }
            // $sql = substr($sql,0,-1);

            //  echo $sql;
            //  $query = $dbLink->prepare($sql);

            // if($query){
            // $debugging = printQuery($debugStat,$sql);
            $debugging = '';

            $insertStatus = DB::table('pdf_annotate')->insert($data);

            if ($insertStatus) {
                return [
                    'success' => true,
                    'message' => "Saved",
                    'debug' => $debugging,
                ];
            } else {
                return [
                    'success' => false,
                    "error" => 3,
                    'message' => "Annotation Can't be Saved after Prepare",
                    'debug' => $debugging,
                ];
            }
            // }else{
            //     return (array(
            //     'success'=>false,
            //     "error"=>3,
            //     'message'=>"Annotation Can't be Saved"
            //     ];
            // }
        } else {
            $data = [
                'success' => true,
                'message' => "no new annotation saved",
            ];
            return $data;
        }
    }

    public function updateModifiedAnnotations($items, $fileId, $username, $debugStat)
    {
        // dd($items, $fileId, $username, $debugStat);
        $affected = 0;
        $data = [];
        if (!empty($items)) {
            foreach ($items as $key => $item) {
                $style = json_encode(@$item['style']);

                if (!$style) $style = '';

                if (@$item['hide'] == "true")
                    $displayStat = "Hidden";
                else
                    $displayStat = "Show";

                $arrres = explode('<div', $item['content']);

                if (count($arrres) > 1) {
                    $arrres = $arrres[1];
                    $arrres = explode("red;padding:2px;\">", $arrres);
                    if (count($arrres) > 1) {
                        $arrres = explode("</div", $arrres[1]);
                        $item['content'] = $arrres[0];
                    } else {
                        $item['content'] = $arrres[1];
                    }
                }
                $content = $item['content'];
                $rect = $item['rect'];
                $rect_real = @$item['rect_real'];
                // $updateStatus = DB::table('pdf_annotate')->update($data);

                $dataUpdate = [
                    'annotate_rect' => '[' . @$rect[0] . ',' . @$rect[1] . ',' . @$rect[2] . ',' . @$rect[3] . ']',
                    'annotate_rect_real' => '[' . @$rect_real[0] . ',' . @$rect_real[1] . ',' . @$rect_real[2] . ',' . @$rect_real[3] . ']',
                    'annotate_content' => $content,
                    'deg' => @$item['deg'] ?? 0,
                    'style' => $style,
                    'status' => $displayStat,
                ];

                $annotateUpdate = DB::table('pdf_annotate')
                    ->where('file_id', $fileId)
                    ->where('annotate_id', $item['id'])
                    ->update($dataUpdate);


                // echo $sql;
                // $query = $dbLink->prepare($sql);

                // if($query){
                // $debugging = printQuery($debugStat,$sql);
                //     $status = mysqli_query($dbLink,$sql);
                //     if($status){
                $affected++;
                //     }
                // }
            }
            $debugging = '';
            if ($affected > 0) {
                $data = [
                    'success' => true,
                    'message' => $affected . " annotations changed",
                    'debug' => $debugging
                ];
            } else {
                $data = [
                    'success' => true,
                    'message' => "no modified annotation",
                ];
            }
        } else {
            $data = [
                'success' => true,
                'message' => "no modified annotation",
            ];
        }
        return $data;
    }

    public function deleteAnnotations($items, $fileId, $username, $debugStat)
    {
        $affected = 0;
        if (!empty($items)) {
            foreach ($items as $key => $item) {

                $deleteAnnotate = DB::table('pdf_annotate')
                    ->where('annotate_id', $item['id'])
                    ->orWhere('parent', $item['id'])
                    ->delete();
                // ->update(['status' => 'Hidden']); //soft deleted

                if ($deleteAnnotate) {
                    $affected++;
                }
            }
        }

        $debugging = '';
        if ($affected > 0) {
            //return true;
            return (array(
                'success' => true,
                'message' => $affected . " annotations deleted",
                'debug' => $debugging
            ));
        } else {
            //return false;
            return (array(
                'success' => false,
                'message' => "no deleted annotation"
            ));
        }
    }

    public function getAnnotationID(Request $request)
    {
        $annotation = $request->annotation;
        $file_id = $request->file_id;
        $rect = $annotation['rect'];
        $rect_real = $annotation['rect_real'];

        $content = $annotation['content'];

        $style = @$annotation['style'] ? json_encode($annotation['style']) : 'null';
        $parentAnnt = @$annotation['parent'] ? $annotation['parent'] : null;
        $displayStat = $annotation['hide'] == 'true' ? "Hidden" : "Show";

        $annotation = DB::table('pdf_annotate')
            ->select(
                'annotate_id'
            )
            ->where('file_id', $file_id)
            ->where('annotate_type', $annotation['name'])
            ->where('annotate_title', $annotation['person'])
            ->where('annotate_content', $content)
            ->where('annotate_rect', '[' . @$rect[0] . ',' . @$rect[1] . ',' . @$rect[2] . ',' . @$rect[3] . ']')
            ->where('annotate_rect_real', '[' . @$rect_real[0] . ',' . @$rect_real[1] . ',' . @$rect_real[2] . ',' . @$rect_real[3] . ']')
            ->where('annotate_page', $annotation['page'])
            ->where('person_id', $annotation['person'])
            ->where('parent', $parentAnnt)
            ->where('deg', @$annotation['deg'] ?? 0)
            ->where('style', $style)
            ->where('status', $displayStat)
            ->first();

        if ($annotation) {
            $data = [
                "status" => "success",
                "ID" => $annotation->annotate_id,
            ];
        } else {
            $data = [
                "status" => "failed",
                "error" => "no matched ID found",
            ];
        }

        return response()->json($data);
    }

    public function countAnnotOnCurrentPage(Request $request)
    {
        if ($request->page && $request->fileId) {
            $annotation = DB::table('pdf_annotate')
                ->where('file_id', $request->fileId)
                ->where('annotate_page', $request->page)
                ->count();

            return $annotation;
        } else {
            return "Not Found";
        }
    }

    public function getSignatureProperties(Request $request)
    {
        // $person = $request->person;

        // if ($person != null) {
        //     $signature = DB::table('ts_org_person')
        //         ->select(
        //             'person_no',
        //             'person_id',
        //             'person_name',
        //             'signature',
        //             'signature_ext',
        //         )
        //         ->where('person_id', $request->person)
        //         ->where('person_active', 1)
        //         ->first();

        //     if ($signature) {
        $imageData = file_get_contents('https://w7.pngwing.com/pngs/86/846/png-transparent-signature-signature-angle-text-monochrome-thumbnail.png');
        $image = imagecreatefromstring($imageData);
        $width = imagesx($image);
        $height = imagesy($image);

        return $width . "|||" . $height;
        //     }
        // } else {
        //     return "Oops something went wrong !!!";
        // }
    }

    public function downloadAnnotatedPdf(Request $request)
    {
        $file_id = @$request->file_id;
        $file_url = @$request->file_url;
        $pixel = @explode('x', $request->pixel);

        if (empty($file_id) && empty($file_url)) {
            return 'Wrong url parameter!';
        }

        if ($file_id) {
            $file = DB::table('ma_file_manager')
                ->select('*')
                ->where('id', $file_id)
                ->first();
        } else if ($file_url) {
            $file = DB::table('ma_file_manager')
                ->select('*')
                ->where('file_url', $file_url)
                ->first();
        }

        if (empty($file)) {
            return 'File data not found!';
        }

        $file_id = $file->id;
        $file_name = $file->file_name;

        // Get Sub Folder Name
        $parsedUrl = parse_url($file->file_url);
        $host_url = $parsedUrl['host'] ?? '';
        $sub_folder = str_replace('.', '-', $host_url);

        $path_folder = storage_path('app/public/' . $sub_folder . '/');

        $source_file = $path_folder . $file_name . '-' . $file->id . '.pdf';
        $source_file_new = $path_folder . $file_name . '-' . $file->id . '-1.4.pdf';

        // self::checkPdfVersion($source_file, $source_file_new);

        $annotations = DB::table('pdf_annotate')
            // ->leftJoin('ts_org_person as tbl2', 'tbl1.person_id', 'tbl2.person_id')
            ->select(
                'annotate_id as id',
                'annotate_type as name',
                'annotate_title as username',
                'annotate_content as content',
                'annotate_rect as rect',
                'annotate_rect_real as rect_real',
                'annotate_page as page',
                'annotate_type as type',
                'person_id',
                // 'tbl2.person_name as title',
                'deg',
                'style',
                'status',
                'parent',
            )
            ->where('file_id', $file_id)
            ->where('status', 'Show')
            ->get();

        $engine = 'Fpdi';
        $method = 'annotatedPdf' . $engine;

        if (method_exists(__CLASS__, $method)) {
            return self::$method($file, $annotations, $path_folder, $pixel); // Call the method dynamically
        } else {
            return abort(403, "Method $method does not exist.");
        }
    }

    public function checkPdfVersion($source_file, $source_file_new)
    {
        if (!file_exists($source_file)) {
            abort(403, "checkPdfVersion not found file::" . $source_file);
        }

        // read pdf file first line because pdf first line contains pdf version information
        $filepdf = fopen($source_file, "r");
        if ($filepdf) {
            $line_first = fgets($filepdf);
            fclose($filepdf);
        } else {
            echo "error opening the file.";
        }

        // extract number such as 1.4,1.5 from first read line of pdf file
        preg_match_all('!\d+!', $line_first, $matches);

        // save that number in a variable
        $pdfversion = implode('.', $matches[0]);
        // dd($pdfversion);
        if ($pdfversion > "1.4") {
            // /var/www/html/meindo-annotation/storage/app/public/unec-edu-az/pdf-sample-20.pdf
            echo "tidak bisa edit pdf diatas 1.4 pdf yang akan di edit versi:" . $pdfversion;
            // exit();
            // USE GHOSTSCRIPT IF PDF VERSION ABOVE 1.4 AND SAVE ANY PDF TO VERSION 1.4 , SAVE NEW PDF OF 1.4 VERSION TO NEW PATH
            // dd($source_file_new);
            $run_script = 'gs -dBATCH -dNOPAUSE -dQUIET -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile="' . $source_file . '" "' . $source_file . '"';

            // $run_script = 'gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile="' . $source_file . '" "' . $source_file . '" version=1.4';
            $a = shell_exec($run_script);
            echo "<br>run_script:" . $run_script;
            echo "<br>convert" . $a;
            // exit();
        }
    }

    public function annotatedPdfMpdf($file, $annotations)
    {
        // $source_file = public_path() . '/document/pdf/' . $filename;
        // $source_file_new = public_path() . '/document/pdf/annotated/' . $filename;

        // dd($file, $source_file_new, $annotations);
        $file_name = $file->file_name;

        // Get Sub Folder Name
        $parsedUrl = parse_url($file->file_url);
        $host_url = $parsedUrl['host'] ?? '';
        $sub_folder = str_replace('.', '-', $host_url);

        $source_file = storage_path('app/public/' . $sub_folder . '/' . $file_name . '-' . $file->id . '.pdf');

        // Initialize mPDF
        $mpdf = new Mpdf([
            'tempDir' => storage_path('app/tmp'), // Set a writable directory
            // 'format' => [99.836111111111, 152.4] // hardcode
        ]);
        $pageCount = $mpdf->SetSourceFile($source_file);

        $annotation_per_page = [];
        foreach ($annotations as $key => $annotation) {
            $annotation_per_page[$annotation->page][] = $annotation;
        }

        for ($i = 1; $i <= $pageCount; $i++) {
            $tplId = $mpdf->ImportPage($i);
            $size = $mpdf->GetTemplateSize($tplId);
            // dd($size);
            $mpdf->AddPage($size['orientation'], '', '', '', '', 0, 0, 0, 0, 0, 0, $size['width'], $size['height'], [10, 10]);
            // $mpdf->AddPage('P','','','','',50,50,50,50,10,10);
            $mpdf->UseTemplate($tplId);

            if (@$annotation_per_page[$i]) {
                self::addAnnotation($mpdf, $annotation_per_page[$i], $size);
            }
        }

        // $mpdf->OutputHttpDownload($file_name.'.pdf');
        $mpdf->OutputHttpInline();

        // unlink($tempFile);

        // Return the generated PDF for download
        // return response()->download($pdfPath);
    }

    public function annotatedPdfFpdi($file, $annotations, $path_folder, $pixel)
    {
        $file_name = $file->file_name;

        $source_file = $path_folder . $file_name . '-' . $file->id . '.pdf';
        $source_file_new = $path_folder . 'final/' . $file_name . '-' . $file->id . '.pdf';

        // initiate FPDI
        $pdf = new Fpdi();
        // remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $annotation_per_page = [];
        foreach ($annotations as $key => $annotation) {
            $annotation_per_page[$annotation->page][] = $annotation;
        }

        $pageCount = $pdf->setSourceFile($source_file);
        $pdf->SetFont("helvetica", "", 20);
        $pdf->SetTextColor(255, 0, 0);
        $pdf->SetFillColor(255, 0, 0);
        $pdf->SetMargins(0, 0, 0, 0);

        // $page_pixel = self::getPagePixel($file, $path_folder);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->ImportPage($pageNo);
            $size = $pdf->GetTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
            if (@$annotation_per_page[$pageNo]) {
                self::addAnnotation($pdf, $annotation_per_page[$pageNo], $size, $pixel);
            }
        }

        // Set headers and output PDF for download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="output.pdf"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . strlen($pdf->Output($source_file_new, 'S')));
        // echo $pdf->Output('', 'S');
        $pdf->Output($source_file_new, 'I');
        // $pdf->Output($source_file_new, 'FI');
    }

    public function addAnnotation($pdf, $annotations, $page_size, $page_pixel)
    {
        $dpi = 147;
        // $dpi = 98;
        // $dpi = 150;
        // dd($page_size);
        $page_width = $page_size['width']; //210 - page width
        // $page_width_pixel = $page_width * $dpi / 25.4; // page width pixel
        // $page_width_pixel = $page_pixel['width'] / 2; // page width pixel
        $page_width_pixel = $page_pixel[0];
        $page_height_pixel = $page_pixel[1];

        // 577.79166666667 x 882.0 pdf

        // 578 x 882 = 147dpi png
        // 283 x 432 = 72dpi png
        $page_height = $page_size['height']; //297 - page height
        // $page_height_pixel = $page_height * $dpi / 25.4; // page height pixel
        // $page_height_pixel = $page_pixel['height']  / 2; // page height pixel
        // dd($page_width_pixel * 2 + 24, $page_height_pixel * 2 + 36);

        $pxl_per_mm_w = $page_width_pixel / $page_width;
        $pxl_per_mm_h = $page_height_pixel / $page_height;

        // dd($page_width, $page_height);

        if ($annotations) {
            foreach ($annotations as $annotation) {
                $pdf->SetTextColor(255, 0, 0);
                $pdf->SetFillColor(255, 0, 0);
                $pdf->SetDrawColor(255, 0, 0);
                $pdf->SetLineWidth(0.5);
                if ($annotation->parent) {
                    $annotation_parent = DB::table('pdf_annotate')->select('*')->where('annotate_id', $annotation->parent)->first();
                    $rect = json_decode($annotation_parent->annotate_rect_real);
                    // dd($rect);
                } else {
                    $rect = json_decode($annotation->rect_real);
                }

                switch ($annotation->type) {
                    case 'Rectangle':
                        list($x1, $y1, $x2, $y2) = $rect;

                        $w = ($x2 - $x1) / $pxl_per_mm_w;
                        $h = ($y2 - $y1) / $pxl_per_mm_h;
                        $x1 = ($x1) / $pxl_per_mm_w;
                        $y1 = ($y1) / $pxl_per_mm_h;
                        // dd($x1, $y1, $w, $h);
                        $pdf->Rect($x1, $y1, $w, $h, 'D');
                        break;
                    case 'Line':
                        $style = json_decode($annotation->style);

                        list($x1, $y1, $x2, $y2) = $rect;
                        $x1 = $x1 / $pxl_per_mm_w + 3;
                        $y1 = $y1 / $pxl_per_mm_h + 3;
                        $x2 = $x2 / $pxl_per_mm_w + 3;
                        $y2 = $y2 / $pxl_per_mm_h + 3;

                        if ($style->linePos == 1) {
                            $pdf->Line($x1, $y1, $x2, $y2);
                        } else {
                            $pdf->Line($x2, $y1, $x1, $y2); // line from right
                        }
                        break;
                    case 'Comment':
                        list($x1, $y1) = $rect;

                        $x1 = $x1 / $pxl_per_mm_w;
                        $y1 = $y1 / $pxl_per_mm_h;

                        $pdf->Annotation($x1, $y1 - 2, 0, 0, $annotation->content, array('Subtype' => 'Text', 'Name' => 'Comment', 'T' => $annotation->username, 'Subj' => 'example', 'C' => array(255, 255, 0)));
                        break;
                    case 'Reply':
                        list($x1, $y1) = $rect;

                        $x1 = $x1 / $pxl_per_mm_w;
                        $y1 = $y1 / $pxl_per_mm_h;

                        $pdf->Annotation($x1, $y1, 0, 0, $annotation->content, array('Subtype' => 'Text', 'Name' => 'Comment', 'T' => $annotation->username, 'Subj' => 'example', 'C' => array(255, 255, 0)));
                        break;
                    case 'Circle':
                        list($x1, $y1, $x2, $y2) = $rect;

                        $xc = ($x1 + (($x2 - $x1) / 2)) / $pxl_per_mm_w + 1;
                        $yc = ($y1 + (($y2 - $y1) / 2)) / $pxl_per_mm_h + 2;

                        $rw = (($x2 - $x1) / 2 + 2) / $pxl_per_mm_w;
                        $rh = (($y2 - $y1) / 2 + 2) / $pxl_per_mm_h;

                        $pdf->Ellipse($xc, $yc, $rw, $rh);
                        break;
                    case 'Signature':
                        list($x1, $y1, $x2, $y2) = $rect;

                        // $img_w = 500;
                        // $img_h = 150;
                        // $w = $img_w / $pxl_per_mm_w;
                        // $h = $img_h / $pxl_per_mm_h;
                        // $x1 = ($x1 + 2) / $pxl_per_mm_w;
                        // $y1 = ($y1 + 3) / $pxl_per_mm_h;


                        $w = ($x2 - $x1) / $pxl_per_mm_w;
                        $h = ($y2 - $y1) / $pxl_per_mm_h;
                        $x1 = ($x1) / $pxl_per_mm_w + 2;
                        $y1 = ($y1) / $pxl_per_mm_h + 3;

                        // $path = $annotation->content;
                        $path = public_path('img/signature/signature.png');
                        // dd($path);

                        $pdf->Image($path, $x1, $y1, $w, $h);
                        break;
                    case 'Arrow':
                        list($x1, $y1, $x2, $y2) = $rect;
                        // dd($x1, $y1, $x2, $y2);
                        //[153.5,446,253.5,496]

                        $w = ($x2 - $x1) / $pxl_per_mm_w;
                        $h = ($y2 - $y1) / $pxl_per_mm_h;
                        $x1 = ($x1) / $pxl_per_mm_w + 2;
                        $y1 = ($y1) / $pxl_per_mm_h + 3;
                        // dd($w,$h);
                        $path = public_path('vendor/pdfviewer/web/images/arrow-annotation.png');

                        // $pdf->Image($path, $x1, $y1, $w, $h);
                        break;
                    case 'Textbox':
                        list($x1, $y1, $x2, $y2) = $rect;
                        $style = json_decode($annotation->style);

                        $w = ($x2 - $x1) / $pxl_per_mm_w;
                        $h = ($y2 - $y1) / $pxl_per_mm_h;
                        $x = ($x1) / $pxl_per_mm_w + 3;
                        $y = ($y1) / $pxl_per_mm_h + 4;

                        // $x = 10; // X position
                        // $y = 20; // Y position
                        // $width = 150; // Width of the border box
                        // $height = 20; // Height of the border box
                        // dd($x1, $y1, $x2, $y2, $pxl_per_mm_w, $pxl_per_mm_h);
                        // dd($x, $y, $w, $h);
                        // dd($page_size);
                        // Draw the border around the text
                        $pdf->SetFont('Times', '', 10.5); // Font Family, , Size
                        $pdf->SetLineWidth(0.1); // Border thickness
                        // $pdf->Rect($x, $y, 51, 5); // Draw the rectangle

                        // Position the text inside the border
                        // $pdf->SetXY(0, 260); // Slightly offset from the border

                        $text = '<div style="' . $style->border . '">' . $annotation->content . '</div>';
                        // dd($text);
                        // Add the text annotation
                        $pdf->writeHTMLCell(10, 0, $x, $y, $text, 1, 0, 0, true, 'J', true);
                        // $pdf->writeHTMLCell(0, 0, 200, 250, $annotation->content, 0, 1, 0, true, '', true);

                        break;
                    case 'Highlight':
                        list($x1, $y1, $x2, $y2) = $rect;

                        $w = ($x2 - $x1) / $pxl_per_mm_w + 1;
                        $h = ($y2 - $y1) / $pxl_per_mm_h + 1;
                        $x1 = ($x1) / $pxl_per_mm_w + 1;
                        $y1 = ($y1) / $pxl_per_mm_h + 2;

                        $pdf->SetAlpha(0.5);
                        $pdf->Rect($x1, $y1, $w, $h, 'F', '', array(255, 255, 153));
                        break;
                }
            }

            //     // $mpdf->Annotation(
            //     //     "Text annotation example\nCharacters test:\xd1\x87\xd0\xb5 \xd0\xbf\xd1\x83\xd1\x85\xd1\x8a\xd1\x82",
            //     //     145, 24, 'Comment', "Ian Back", "My Subject",
            //     //     0.7, array(127, 127, 255)
            //     // );
        }
    }

    public function getPagePixel($file, $path_folder)
    {
        $file_name = $file->file_name;

        $source_file = $path_folder . $file_name . '-' . $file->id . '.pdf';
        $source_file_new = $path_folder . 'temp-png/' . $file_name . '-' . $file->id . '.png';

        // if (!file_exists($source_file_new)) {
        $new_temp_path = $path_folder . 'temp-png/';

        if (!file_exists($new_temp_path)) {
            mkdir($new_temp_path, 0777, true);
        }
        // -resize WIDTHxHEIGHT
        $run_script = 'convert -density 72 ' . $source_file . '[0] ' . $source_file_new;
        // $run_script = 'magick '. $source_file .'[0] -format "%x x %y" info:';
        // $run_script = 'convert '. $source_file .'[0] -resize ' . $page_size['width'] . 'x' . $page_size['height'] . ' ' . $source_file_new;
        // dd($run_script);
        $a = shell_exec($run_script);
        // }

        $image = imagecreatefrompng($source_file_new);
        $page_pixel['width'] = imagesx($image);
        $page_pixel['height'] = imagesy($image);
        // dd($page_pixel);

        imagedestroy($image);

        return $page_pixel;
    }

    public function getResponseUrl(Request $request)
    {
        $file_url = $request->file_url;
        $refresh = @$request->refresh;

        $file_data = DB::table('ma_file_manager')->where('file_url', $file_url)->first();

        // Get Sub Folder Name
        $parsedUrl = parse_url($file_url);
        $host_url = $parsedUrl['host'] ?? '';
        $sub_folder = str_replace('.', '-', $host_url);

        // Jika hit melalui viewer akan selalu masuk ke kondisi if pertama
        // Karena data sudah di save pada fungsi getFiledata
        // Jika hit melalui url langsung akan ke fungsi else
        if ($file_data) {

            $file_name = $file_data->file_name;

            if ($refresh) {
                $path = storage_path('app/public/' . $sub_folder);

                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                $filePath = $sub_folder . '/' . $file_name . '-' . $file_data->id . '.pdf';
                if (!Storage::disk('public')->exists($filePath)) {
                    $response_get_url = Http::get($file_url);

                    if ($response_get_url->successful()) {
                        Storage::disk('public')->put($filePath, $response_get_url->body());
                        chmod(Storage::disk('public')->path($filePath), 0777);
                    }
                }
            }

            $source_file = Storage::disk('public')->get($sub_folder . '/' . $file_name . '-' . $file_data->id . '.pdf');

            $response = $source_file;
        } else {
            $response = Http::timeout(120)->get($file_url);

            if ($refresh) {
                $path = storage_path('app/public/' . $sub_folder);

                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                $file = DB::table('ma_file_manager')
                    ->select('id', 'file_url', 'file_name')
                    ->where('file_url', $file_url)
                    ->first();

                $filePath = $sub_folder . '/' . $file->file_name . '-' . $file->id . '.pdf';
                if (!Storage::disk('public')->exists($filePath)) {

                    if ($response->successful()) {
                        Storage::disk('public')->put($filePath, $response->body());
                        chmod(Storage::disk('public')->path($filePath), 0777);
                    }
                }
            }
            $response = $response->body();
        }

        return $response;
    }
}
