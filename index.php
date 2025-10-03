<?php
// list.php - Halaman untuk melihat semua data yang sudah diinput
header("Content-Type: text/html; charset=UTF-8");

// Koneksi ke database
$host = "localhost";
$username = "root";
$password = "";
$database = "sistem_pakar_tms";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Ambil semua data
$stmt = $pdo->query("
    SELECT 
        r.id,
        r.nama,
        r.lokasi,
        r.jenis_bencana,
        r.tingkat_risiko,
        rt.tms_id,
        rt.tms_nama,
        rt.skor_lokasi,
        rt.skor_sdm,
        rt.skor_material,
        rt.kategori_lokasi,
        rt.kategori_sdm,
        rt.kategori_material,
        rt.created_at
    FROM responden r
    JOIN rekomendasi_tms rt ON r.id = rt.responden_id
    ORDER BY rt.created_at DESC
");

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List Data TMS - Sistem Pakar</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #ebecea 0%, #95b0c6 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        h1 {
            color: #051c32;
            font-size: 1.8rem;
            margin-bottom: 8px;
            background: #1c377c;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            color: #7f8c8d;
            font-size: 1rem;
            margin-bottom: 8px;
        }

        .back-btn {
            display: inline-block;
            background: #1c377c;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            text-decoration: none;
            margin-top: 15px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: #152a5e;
            transform: translateY(-2px);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #1c377c;
        }

        .data-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #1c377c;
            color: white;
            font-weight: 600;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-tinggi { background: #b3e6cc; color: #155724; }
        .badge-sedang { background: #fff3cd; color: #856404; }
        .badge-rendah { background: #f8d7da; color: #721c24; }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        @media (max-width: 768px) {
            .data-table {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
            
            th, td {
                padding: 8px 10px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 Data Hasil Penilaian TMS</h1>
            <p class="subtitle">Daftar semua responden yang telah melakukan penilaian</p>
        </header>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($data); ?></div>
                <div>Total Responden</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $uniqueTMS = array_unique(array_column($data, 'tms_id'));
                    echo count($uniqueTMS);
                    ?>
                </div>
                <div>Jenis TMS Direkomendasikan</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php
                    $recent = array_filter($data, function($item) {
                        return strtotime($item['created_at']) >= strtotime('-7 days');
                    });
                    echo count($recent);
                    ?>
                </div>
                <div>Penilaian 7 Hari Terakhir</div>
            </div>
        </div>

        <div class="data-table">
            <?php if (count($data) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Lokasi</th>
                            <th>Bencana</th>
                            <th>Rekomendasi TMS</th>
                            <th>Skor</th>
                            <th>Kategori</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nama']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['lokasi']); ?></td>
                            <td><?php echo htmlspecialchars($row['jenis_bencana']); ?> (<?php echo htmlspecialchars($row['tingkat_risiko']); ?>)</td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['tms_id']); ?></strong><br>
                                <small><?php echo htmlspecialchars($row['tms_nama']); ?></small>
                            </td>
                            <td>
                                L: <?php echo $row['skor_lokasi']; ?><br>
                                S: <?php echo $row['skor_sdm']; ?><br>
                                M: <?php echo $row['skor_material']; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $row['kategori_lokasi']; ?>">L: <?php echo ucfirst($row['kategori_lokasi']); ?></span><br>
                                <span class="badge badge-<?php echo $row['kategori_sdm']; ?>">S: <?php echo ucfirst($row['kategori_sdm']); ?></span><br>
                                <span class="badge badge-<?php echo $row['kategori_material']; ?>">M: <?php echo ucfirst($row['kategori_material']); ?></span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <h3>📝 Belum Ada Data</h3>
                    <p>Belum ada responden yang melakukan penilaian.</p>
                    <a href="index.html" class="back-btn">Isi Form Penilaian Pertama</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>