<?php
session_start();
$password = '1234';

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['logged_in'] = true;
    } else {
        $error = "Senha incorreta!";
    }
}

// Criar pasta de uploads
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Processar Upload
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_FILES['fotos'])) {
    $successCount = 0;
    foreach ($_FILES['fotos']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['fotos']['error'][$key] === UPLOAD_ERR_OK) {
            $name = basename($_FILES['fotos']['name'][$key]);
            // Cria um nome único com timestamp para não sobrescrever imagens com mesmo nome
            $safeName = time() . '_' . rand(100, 999) . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "", $name);
            if(move_uploaded_file($tmp_name, $uploadDir . $safeName)) {
                $successCount++;
            }
        }
    }
    if ($successCount > 0) {
        $msg = "$successCount foto(s) adicionada(s) com sucesso!";
    } else {
        $error = "Nenhuma foto foi enviada. Verifique o tamanho do arquivo.";
    }
}

// Processar Exclusão
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_POST['delete'])) {
    $fileToDelete = $_POST['delete'];
    // Garante que o arquivo está na pasta uploads para segurança
    if (file_exists($fileToDelete) && strpos($fileToDelete, 'uploads/') === 0) {
        unlink($fileToDelete);
        $msg = "Foto removida com sucesso!";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Fotos - RMJ Soluções</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: #0a0a0c; 
            color: #fff; 
            padding: 2rem 1rem; 
            max-width: 800px; 
            margin: 0 auto; 
        }
        .box { 
            background: #151518; 
            padding: 2rem; 
            border-radius: 12px; 
            border: 1px solid rgba(255,255,255,0.05); 
        }
        h2 { margin-top: 0; margin-bottom: 1.5rem; color: #fff; }
        input[type="password"], input[type="file"] { 
            background: #0a0a0c;
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            padding: 1rem; 
            border-radius: 8px;
            width: 100%; 
            box-sizing: border-box; 
            margin-bottom: 1rem;
        }
        button { 
            background: #EC3238; 
            color: white; 
            border: none; 
            padding: 1rem; 
            font-size: 1rem;
            font-weight: 600; 
            cursor: pointer; 
            border-radius: 8px;
            width: 100%;
            transition: background 0.3s;
        }
        button:hover { background: #d02a30; }
        
        .gallery { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); 
            gap: 15px; 
            margin-top: 1.5rem; 
        }
        .img-wrap { 
            position: relative; 
            border-radius: 8px;
            overflow: hidden;
            aspect-ratio: 1/1;
            background: #000;
        }
        .img-wrap img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            opacity: 0.9;
            transition: opacity 0.3s;
        }
        .img-wrap:hover img { opacity: 0.6; }
        .del-btn { 
            position: absolute; 
            top: 5px; 
            right: 5px; 
            background: rgba(236, 50, 56, 0.9); 
            color: white; 
            border: none; 
            width: 30px;
            height: 30px;
            padding: 0;
            cursor: pointer; 
            font-weight: bold;
            border-radius: 50%; 
            font-size: 14px;
        }
        .del-btn:hover { background: red; }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .alert-error { background: rgba(236, 50, 56, 0.1); border: 1px solid #EC3238; color: #ff8a8c; }
        .alert-success { background: rgba(76, 175, 80, 0.1); border: 1px solid #4caf50; color: #81c784; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .logout-link { color: #89a4e8; text-decoration: none; font-size: 0.9rem; font-weight: 600; }
        .logout-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="box">
        <?php if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true): ?>
            <h2>Acesso Restrito</h2>
            <p style="color: rgba(255,255,255,0.6); margin-bottom: 2rem;">Digite a senha de segurança para gerenciar as fotos do site.</p>
            
            <?php if(isset($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
            
            <form method="POST">
                <input type="password" name="password" placeholder="Senha de acesso" required>
                <button type="submit">Acessar Painel</button>
            </form>
        <?php else: ?>
            <div class="header">
                <h2>Adicionar Fotos</h2>
                <a href="?logout=1" class="logout-link">Sair do painel</a>
            </div>
            
            <?php if(isset($msg)) echo "<div class='alert alert-success'>$msg</div>"; ?>
            <?php if(isset($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <label style="display:block; margin-bottom: 0.5rem; font-size: 0.9rem; color: rgba(255,255,255,0.7);">Selecione uma ou mais fotos:</label>
                <input type="file" name="fotos[]" multiple accept="image/*" required>
                <button type="submit">Fazer Upload das Fotos</button>
            </form>

            <h3 style="margin-top:3rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 2rem;">Fotos Publicadas</h3>
            <p style="font-size: 0.85rem; color: rgba(255,255,255,0.5);">Estas são as fotos que estão aparecendo no site atualmente.</p>
            
            <div class="gallery">
                <?php
                if (is_dir($uploadDir)) {
                    $files = scandir($uploadDir);
                    $hasImages = false;
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..') {
                            $hasImages = true;
                            echo "<div class='img-wrap'>
                                    <img src='$uploadDir$file' alt='Foto da galeria' loading='lazy'>
                                    <form method='POST' style='margin:0;' onsubmit='return confirm(\"Tem certeza que deseja apagar esta foto?\");'>
                                        <input type='hidden' name='delete' value='$uploadDir$file'>
                                        <button type='submit' class='del-btn' title='Apagar foto'>✕</button>
                                    </form>
                                  </div>";
                        }
                    }
                    if (!$hasImages) {
                        echo "<p style='grid-column: 1 / -1; color: rgba(255,255,255,0.4); text-align: center; padding: 2rem 0;'>Nenhuma foto publicada ainda.</p>";
                    }
                }
                ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
