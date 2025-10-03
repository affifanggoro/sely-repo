<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Koneksi ke database
$host = "localhost";
$username = "root";
$password = "";
$database = "sistem_pakar_tms";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(["error" => "Koneksi database gagal: " . $e->getMessage()]);
    exit;
}

// Handle preflight request untuk CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Fungsi untuk menangani POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Log untuk debugging
    error_log("Received data: " . print_r($input, true));
    
    if (isset($input['action'])) {
        switch($input['action']) {
            case 'simpan_penilaian':
                simpanPenilaian($pdo, $input);
                break;
            case 'ambil_riwayat':
                ambilRiwayat($pdo);
                break;
            case 'test':
                echo json_encode([
                    "status" => "success", 
                    "message" => "API is working!",
                    "timestamp" => date('Y-m-d H:i:s')
                ]);
                break;
            default:
                echo json_encode(["error" => "Aksi tidak dikenali: " . $input['action']]);
        }
    } else {
        echo json_encode(["error" => "Aksi tidak ditentukan"]);
    }
} else {
    echo json_encode(["error" => "Metode request tidak diizinkan. Gunakan POST."]);
}

function simpanPenilaian($pdo, $data) {
    try {
        $pdo->beginTransaction();
        
        // Validasi data yang diperlukan
        if (!isset($data['identitas']) || !isset($data['penilaian']) || !isset($data['rekomendasi'])) {
            throw new Exception("Data tidak lengkap. Pastikan identitas, penilaian, dan rekomendasi tersedia.");
        }
        
        // 1. Simpan data responden dengan null safety
        $stmt = $pdo->prepare("
            INSERT INTO responden 
            (nama, umur, jenis_kelamin, pekerjaan, lama_tinggal, lokasi, 
             jenis_bencana, tingkat_risiko, kedekatan_penghidupan, status_lahan) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $identitas = $data['identitas'];
        $stmt->execute([
            $identitas['nama'] ?? '',
            $identitas['umur'] ?? 0,
            $identitas['jenis_kelamin'] ?? '',
            $identitas['pekerjaan'] ?? '',
            $identitas['lama_tinggal'] ?? 0,
            $identitas['lokasi'] ?? '',
            $identitas['jenis_bencana'] ?? '',
            $identitas['tingkat_risiko'] ?? '',
            $identitas['kedekatan_penghidupan'] ?? '',
            $identitas['status_lahan'] ?? ''
        ]);
        
        $responden_id = $pdo->lastInsertId();
        
        // 2. Simpan penilaian kriteria dengan validasi
        $stmtKriteria = $pdo->prepare("
            INSERT INTO penilaian_kriteria (responden_id, kategori, kriteria, skor) 
            VALUES (?, ?, ?, ?)
        ");
        
        $penilaian = $data['penilaian'];
        
        // Validasi dan simpan penilaian lokasi
        if (isset($penilaian['lokasi']) && is_array($penilaian['lokasi'])) {
            foreach($penilaian['lokasi'] as $kriteria => $skor) {
                $stmtKriteria->execute([
                    $responden_id, 
                    'lokasi', 
                    $kriteria, 
                    intval($skor) // Pastikan skor adalah integer
                ]);
            }
        } else {
            throw new Exception("Data penilaian lokasi tidak valid");
        }
        
        // Validasi dan simpan penilaian SDM
        if (isset($penilaian['sdm']) && is_array($penilaian['sdm'])) {
            foreach($penilaian['sdm'] as $kriteria => $skor) {
                $stmtKriteria->execute([
                    $responden_id, 
                    'sdm', 
                    $kriteria, 
                    intval($skor)
                ]);
            }
        } else {
            throw new Exception("Data penilaian SDM tidak valid");
        }
        
        // Validasi dan simpan penilaian material
        if (isset($penilaian['material']) && is_array($penilaian['material'])) {
            foreach($penilaian['material'] as $kriteria => $skor) {
                $stmtKriteria->execute([
                    $responden_id, 
                    'material', 
                    $kriteria, 
                    intval($skor)
                ]);
            }
        } else {
            throw new Exception("Data penilaian material tidak valid");
        }
        
        // 3. Simpan rekomendasi TMS dengan validasi
        $stmtRekomendasi = $pdo->prepare("
            INSERT INTO rekomendasi_tms 
            (responden_id, tms_id, tms_nama, skor_lokasi, kategori_lokasi, 
             skor_sdm, kategori_sdm, skor_material, kategori_material) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $rekomendasi = $data['rekomendasi'];
        $stmtRekomendasi->execute([
            $responden_id,
            $rekomendasi['tms_id'] ?? '',
            $rekomendasi['tms_nama'] ?? '',
            intval($rekomendasi['skor_lokasi'] ?? 0),
            $rekomendasi['kategori_lokasi'] ?? '',
            intval($rekomendasi['skor_sdm'] ?? 0),
            $rekomendasi['kategori_sdm'] ?? '',
            intval($rekomendasi['skor_material'] ?? 0),
            $rekomendasi['kategori_material'] ?? ''
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            "success" => true,
            "message" => "Data berhasil disimpan",
            "responden_id" => $responden_id
        ]);
        
    } catch(Exception $e) {
        $pdo->rollBack();
        error_log("Error in simpanPenilaian: " . $e->getMessage());
        echo json_encode(["error" => "Gagal menyimpan data: " . $e->getMessage()]);
    }
}

function ambilRiwayat($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT r.*, rt.tms_id, rt.tms_nama, rt.created_at as tanggal_rekomendasi
            FROM responden r
            JOIN rekomendasi_tms rt ON r.id = rt.responden_id
            ORDER BY rt.created_at DESC
            LIMIT 10
        ");
        
        $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "success" => true,
            "data" => $riwayat
        ]);
        
    } catch(Exception $e) {
        error_log("Error in ambilRiwayat: " . $e->getMessage());
        echo json_encode(["error" => "Gagal mengambil riwayat: " . $e->getMessage()]);
    }
}
?>