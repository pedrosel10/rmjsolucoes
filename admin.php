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

$featuredFile = 'featured.json';
$featuredPhotos = [];
if (file_exists($featuredFile)) {
    $featuredPhotos = json_decode(file_get_contents($featuredFile), true) ?: [];
}

// Processar Destaques
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_POST['save_featured'])) {
    $selected = isset($_POST['featured']) ? $_POST['featured'] : [];
    if (count($selected) > 4) {
        $selected = array_slice($selected, 0, 4);
        $error = "Apenas 4 fotos podem ser selecionadas. As 4 primeiras foram salvas.";
    } else {
        $msg = "Fotos em destaque atualizadas!";
    }
    file_put_contents($featuredFile, json_encode($selected));
    $featuredPhotos = $selected;
}

// Processar Exclusão
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_POST['delete'])) {
    $fileToDelete = $_POST['delete'];
    // Garante que o arquivo está na pasta uploads para segurança
    if (file_exists($fileToDelete) && strpos($fileToDelete, 'uploads/') === 0) {
        unlink($fileToDelete);
        $msg = "Foto removida com sucesso!";
        if (($key = array_search($fileToDelete, $featuredPhotos)) !== false) {
            unset($featuredPhotos[$key]);
            $featuredPhotos = array_values($featuredPhotos);
            file_put_contents($featuredFile, json_encode($featuredPhotos));
        }
    }
}

// ================= GERENCIAR PRODUTOS =================
$produtosFile = 'produtos.json';
$produtos = [];
if (file_exists($produtosFile)) {
    $produtos = json_decode(file_get_contents($produtosFile), true) ?: [];
}

$uploadProdDir = 'uploads/produtos/';
if (!is_dir($uploadProdDir)) {
    mkdir($uploadProdDir, 0755, true);
}

// Converter produtos antigos caso não tenham o array de images
foreach ($produtos as &$p) {
    if (isset($p['image']) && !isset($p['images'])) {
        $p['images'] = [$p['image']];
        $p['cover_image'] = $p['image'];
    }
}
unset($p);

// Adicionar Produto
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_POST['add_product'])) {
    $pName = trim($_POST['p_name']);
    $pDesc = trim($_POST['p_desc']);
    $pStock = isset($_POST['p_stock']) ? true : false;
    
    $uploadedImages = [];
    if (isset($_FILES['p_fotos'])) {
        foreach ($_FILES['p_fotos']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['p_fotos']['error'][$key] === UPLOAD_ERR_OK) {
                $fotoName = basename($_FILES['p_fotos']['name'][$key]);
                $safeName = time() . '_' . rand(100, 999) . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "", $fotoName);
                $targetFile = $uploadProdDir . $safeName;
                if (move_uploaded_file($tmp_name, $targetFile)) {
                    $uploadedImages[] = $targetFile;
                }
            }
        }
    }
    
    if (count($uploadedImages) > 0) {
        $base_wa = "https://wa.me/5547992108669?text=";
        $msg_wa = "Olá, gostaria de mais informações para adquirir esse produto: " . $pName;
        $wa_link = $base_wa . urlencode($msg_wa);
        
        $newProduct = [
            'id' => time() . rand(10, 99),
            'name' => $pName,
            'description' => $pDesc,
            'in_stock' => $pStock,
            'images' => $uploadedImages,
            'cover_image' => $uploadedImages[0], // Primeira por padrão
            'wa_link' => $wa_link
        ];
        
        array_unshift($produtos, $newProduct);
        file_put_contents($produtosFile, json_encode($produtos));
        $msg = "Produto adicionado com sucesso!";
    } else {
        $error = "Selecione ao menos uma imagem válida para o produto.";
    }
}

// Excluir Produto
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_POST['delete_product'])) {
    $delId = $_POST['delete_product'];
    foreach ($produtos as $k => $p) {
        if ($p['id'] == $delId) {
            if (isset($p['images']) && is_array($p['images'])) {
                foreach ($p['images'] as $img) {
                    if (file_exists($img)) unlink($img);
                }
            } else if (isset($p['image']) && file_exists($p['image'])) {
                unlink($p['image']);
            }
            unset($produtos[$k]);
            $produtos = array_values($produtos);
            file_put_contents($produtosFile, json_encode($produtos));
            $msg = "Produto excluído com sucesso!";
            break;
        }
    }
}

// Definir Capa
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_POST['set_cover_prod_id']) && isset($_POST['set_cover_img'])) {
    $prodId = $_POST['set_cover_prod_id'];
    $coverImg = $_POST['set_cover_img'];
    foreach ($produtos as $k => $p) {
        if ($p['id'] == $prodId) {
            if (in_array($coverImg, $p['images'])) {
                $produtos[$k]['cover_image'] = $coverImg;
                file_put_contents($produtosFile, json_encode($produtos));
                $msg = "Capa do produto atualizada com sucesso!";
            }
            break;
        }
    }
}

// Excluir Foto de Produto
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_POST['delete_prod_img_id']) && isset($_POST['delete_prod_img_src'])) {
    $prodId = $_POST['delete_prod_img_id'];
    $imgSrc = $_POST['delete_prod_img_src'];
    foreach ($produtos as $k => $p) {
        if ($p['id'] == $prodId) {
            $imgIndex = array_search($imgSrc, $p['images']);
            if ($imgIndex !== false) {
                // Se só tiver 1 foto não deixa excluir? Melhor deixar, mas o produto fica sem foto? Vamos forçar ter pelo menos 1
                if (count($p['images']) > 1) {
                    unset($produtos[$k]['images'][$imgIndex]);
                    $produtos[$k]['images'] = array_values($produtos[$k]['images']);
                    if (file_exists($imgSrc)) unlink($imgSrc);
                    // Se excluiu a capa, define a primeira como capa
                    if ($produtos[$k]['cover_image'] === $imgSrc) {
                        $produtos[$k]['cover_image'] = $produtos[$k]['images'][0];
                    }
                    file_put_contents($produtosFile, json_encode($produtos));
                    $msg = "Foto excluída com sucesso!";
                } else {
                    $error = "O produto precisa ter pelo menos uma foto. Exclua o produto se não for mais vendê-lo.";
                }
            }
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin - RMJ Soluções</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0d0d12;
            --surface: #15151a;
            --surface-hover: #1f1f26;
            --border: rgba(255, 255, 255, 0.08);
            --primary: #3C58A5;
            --primary-hover: #5271c4;
            --danger: #EC3238;
            --danger-hover: #ff4b51;
            --text-main: #f8f9fc;
            --text-muted: #a8acbd;
            --radius-md: 12px;
            --radius-sm: 8px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--bg); 
            color: var(--text-main); 
            padding: 2rem; 
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 1100px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(90deg, #fff, #a8acbd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logout-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 8px 16px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
        }
        .logout-link:hover {
            color: var(--text-main);
            background: var(--surface-hover);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 900px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }

        .card { 
            background: var(--surface); 
            padding: 2rem; 
            border-radius: var(--radius-md); 
            border: 1px solid var(--border); 
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-title::before {
            content: '';
            display: block;
            width: 4px;
            height: 20px;
            background: var(--primary);
            border-radius: 4px;
        }
        .card-title.red::before { background: var(--danger); }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        input[type="password"], input[type="file"], input[type="text"], textarea { 
            background: rgba(0,0,0,0.2);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 0.85rem 1rem; 
            border-radius: var(--radius-sm);
            width: 100%; 
            font-family: inherit;
            font-size: 0.95rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(60, 88, 165, 0.2);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .checkbox-group input {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        button { 
            background: var(--primary); 
            color: white; 
            border: none; 
            padding: 0.85rem 1.5rem; 
            font-size: 0.95rem;
            font-weight: 600; 
            cursor: pointer; 
            border-radius: var(--radius-sm);
            transition: all 0.3s ease;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }
        button:hover { 
            background: var(--primary-hover); 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(60, 88, 165, 0.3);
        }
        button.btn-danger {
            background: var(--danger);
        }
        button.btn-danger:hover {
            background: var(--danger-hover);
            box-shadow: 0 4px 12px rgba(236, 50, 56, 0.3);
        }
        
        .gallery { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); 
            gap: 12px; 
            margin-top: 1rem; 
        }
        .gallery-item { 
            position: relative; 
            border-radius: var(--radius-sm);
            overflow: hidden;
            aspect-ratio: 1/1;
            background: #000;
            border: 1px solid var(--border);
        }
        .gallery-item img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            opacity: 0.8;
            transition: opacity 0.3s, transform 0.5s;
        }
        .gallery-item:hover img { 
            opacity: 0.5; 
            transform: scale(1.05);
        }
        .del-btn { 
            position: absolute; 
            top: 6px; 
            right: 6px; 
            background: rgba(236, 50, 56, 0.8); 
            color: white; 
            border: none; 
            width: 28px;
            height: 28px;
            padding: 0;
            cursor: pointer; 
            border-radius: 50%; 
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: scale(0.8);
        }
        .gallery-item:hover .del-btn {
            opacity: 1;
            transform: scale(1);
        }
        .del-btn:hover { background: var(--danger); }
        
        .featured-badge {
            position: absolute;
            bottom: 6px;
            left: 6px;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(4px);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* Products list */
        .prod-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .prod-item {
            background: rgba(0,0,0,0.2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .prod-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
        }
        .prod-info {
            flex-grow: 1;
            min-width: 0;
        }
        .prod-name {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-main);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .prod-status {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4caf50;
        }
        .status-dot.out { background: var(--danger); }
        
        .alert { 
            padding: 1rem; 
            border-radius: var(--radius-sm); 
            margin-bottom: 1.5rem; 
            font-size: 0.95rem;
            font-weight: 500;
        }
        .alert-error { background: rgba(236, 50, 56, 0.1); border: 1px solid rgba(236, 50, 56, 0.3); color: #ff8a8c; }
        .alert-success { background: rgba(76, 175, 80, 0.1); border: 1px solid rgba(76, 175, 80, 0.3); color: #81c784; }
        
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: var(--text-muted);
            font-size: 0.9rem;
            background: rgba(0,0,0,0.2);
            border-radius: var(--radius-sm);
            border: 1px dashed var(--border);
        }

        /* Login Screen */
        .login-box {
            max-width: 400px;
            width: 100%;
            margin: 10vh auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true): ?>
            <div class="card login-box">
                <div class="header" style="border:none; margin-bottom:0; padding-bottom:0;">
                    <h1>Painel Admin</h1>
                </div>
                <p style="color: var(--text-muted); font-size: 0.95rem;">Digite a senha de segurança para acessar o gerenciamento do site.</p>
                
                <?php if(isset($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
                
                <form method="POST" style="display:flex; flex-direction:column; gap:1.5rem;">
                    <div class="form-group">
                        <input type="password" name="password" placeholder="Senha de acesso" required autofocus>
                    </div>
                    <button type="submit" style="width:100%;">Acessar Painel</button>
                </form>
            </div>
        <?php else: ?>
            
            <div class="header">
                <h1>Painel Administrativo</h1>
                <a href="?logout=1" class="logout-link">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Sair
                </a>
            </div>

            <?php if(isset($msg)) echo "<div class='alert alert-success'>$msg</div>"; ?>
            <?php if(isset($error)) echo "<div class='alert alert-error'>$error</div>"; ?>
            
            <div class="dashboard-grid">
                
                <!-- COLUNA 1: FOTOS -->
                <div style="display:flex; flex-direction:column; gap:2rem;">
                    
                    <!-- Adicionar Fotos -->
                    <div class="card">
                        <div class="card-title">Adicionar Fotos</div>
                        <form method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:1.5rem;">
                            <div class="form-group">
                                <label>Selecione uma ou mais imagens para a galeria:</label>
                                <input type="file" name="fotos[]" multiple accept="image/*" required>
                            </div>
                            <button type="submit" style="width:100%;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                Fazer Upload
                            </button>
                        </form>
                    </div>

                    <!-- Gerenciar Fotos -->
                    <div class="card">
                        <div class="card-title">Fotos da Galeria</div>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top:-10px;">Marque até 4 fotos para aparecerem em destaque na home.</p>
                        
                        <form method="POST">
                            <button type="submit" name="save_featured" value="1" style="margin-bottom: 1rem;">Salvar Destaques</button>
                            <div class="gallery">
                                <?php
                                if (is_dir($uploadDir)) {
                                    $files = scandir($uploadDir);
                                    $imageFiles = [];
                                    foreach ($files as $file) {
                                        if ($file !== '.' && $file !== '..') {
                                            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                                                $imageFiles[] = $file;
                                            }
                                        }
                                    }
                                    rsort($imageFiles);

                                    if (count($imageFiles) > 0) {
                                        foreach ($imageFiles as $file) {
                                            $filePath = $uploadDir . $file;
                                            $isChecked = in_array($filePath, $featuredPhotos) ? 'checked' : '';
                                            echo "<div class='gallery-item'>
                                                    <img src='$filePath' alt='Foto' loading='lazy'>
                                                    <div class='featured-badge'>
                                                        <input type='checkbox' name='featured[]' value='$filePath' $isChecked> Destaque
                                                    </div>
                                                    <button type='submit' name='delete' value='$filePath' class='del-btn' title='Apagar' onclick='return confirm(\"Apagar esta foto?\");'>✕</button>
                                                  </div>";
                                        }
                                    } else {
                                        echo "<div class='empty-state' style='grid-column: 1 / -1;'>Nenhuma foto publicada ainda.</div>";
                                    }
                                }
                                ?>
                            </div>
                        </form>
                    </div>

                </div>

                <!-- COLUNA 2: PRODUTOS -->
                <div style="display:flex; flex-direction:column; gap:2rem;">
                    
                    <!-- Adicionar Produto -->
                    <div class="card">
                        <div class="card-title red">Novo Produto (E-commerce)</div>
                        <form method="POST" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:1.2rem;">
                            <div class="form-group">
                                <label>Nome do Produto:</label>
                                <input type="text" name="p_name" required placeholder="Ex: Telha Translúcida">
                            </div>
                            
                            <div class="form-group">
                                <label>Descrição do Produto:</label>
                                <textarea name="p_desc" required rows="3" placeholder="Detalhes do produto..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Foto(s) do Produto:</label>
                                <input type="file" name="p_fotos[]" multiple accept="image/*" required>
                            </div>
                            
                            <label class="checkbox-group">
                                <input type="checkbox" name="p_stock" checked> Produto em estoque
                            </label>
                            
                            <button type="submit" name="add_product" value="1" class="btn-danger" style="margin-top:0.5rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                                Cadastrar Produto
                            </button>
                        </form>
                    </div>

                    <!-- Gerenciar Produtos -->
                    <div class="card">
                        <div class="card-title red">Produtos Cadastrados</div>
                        
                        <div class="prod-list">
                            <?php if(!empty($produtos)): ?>
                                <?php foreach($produtos as $prod): ?>
                                    <div class="prod-item" style="flex-direction:column; align-items:flex-start;">
                                        <div style="display:flex; width:100%; gap:12px; align-items:center;">
                                            <div class="prod-info">
                                                <div class="prod-name" title="<?= htmlspecialchars($prod['name']) ?>"><?= htmlspecialchars($prod['name']) ?></div>
                                                <div class="prod-status">
                                                    <div class="status-dot <?= $prod['in_stock'] ? '' : 'out' ?>"></div>
                                                    <?= $prod['in_stock'] ? 'Em estoque' : 'Esgotado' ?>
                                                </div>
                                            </div>
                                            <form method="POST" style="margin:0;">
                                                <button type="submit" name="delete_product" value="<?= $prod['id'] ?>" class="btn-danger" style="padding: 8px 12px; font-size: 0.85rem;" onclick="return confirm('Apagar este produto?');">
                                                    Excluir Produto
                                                </button>
                                            </form>
                                        </div>
                                        
                                        <!-- Galeria do Produto -->
                                        <div style="display:flex; gap:10px; overflow-x:auto; width:100%; padding-top:10px; border-top:1px solid var(--border); margin-top:5px;">
                                            <?php 
                                                $images = isset($prod['images']) ? $prod['images'] : [$prod['image']];
                                                $cover = isset($prod['cover_image']) ? $prod['cover_image'] : $images[0];
                                                foreach($images as $img): 
                                                    $isCover = ($img === $cover);
                                            ?>
                                                <div style="position:relative; width:80px; min-width:80px; height:80px; border-radius:6px; overflow:hidden; border: <?= $isCover ? '2px solid var(--primary)' : '1px solid transparent' ?>;">
                                                    <img src="<?= $img ?>" style="width:100%; height:100%; object-fit:cover; border-radius:0;">
                                                    <?php if($isCover): ?>
                                                        <div style="position:absolute; bottom:0; left:0; width:100%; background:rgba(0,0,0,0.7); color:#fff; font-size:10px; text-align:center; padding:3px 0; font-weight:bold;">★ CAPA</div>
                                                    <?php else: ?>
                                                        <!-- Botão para setar capa -->
                                                        <form method="POST" style="position:absolute; bottom:0; left:0; width:100%; margin:0;">
                                                            <input type="hidden" name="set_cover_prod_id" value="<?= $prod['id'] ?>">
                                                            <button type="submit" name="set_cover_img" value="<?= $img ?>" style="width:100%; padding:3px 0; font-size:10px; border-radius:0; background:rgba(0,0,0,0.7); color:#fff; border:none; line-height:1;">Definir Capa</button>
                                                        </form>
                                                        <!-- Botão para excluir foto -->
                                                        <form method="POST" style="position:absolute; top:2px; right:2px; margin:0;">
                                                            <input type="hidden" name="delete_prod_img_id" value="<?= $prod['id'] ?>">
                                                            <button type="submit" name="delete_prod_img_src" value="<?= $img ?>" style="padding:2px 5px; font-size:10px; border-radius:50%; background:rgba(236,50,56,0.9); color:#fff; border:none; line-height:1; min-width:auto;" onclick="return confirm('Excluir esta foto?');">✕</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">Nenhum produto cadastrado.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            </div>
        <?php endif; ?>
    </div>
</body>
</html>
