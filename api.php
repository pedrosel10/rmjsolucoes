<?php
header('Content-Type: application/json');

// Permitir requisições locais (CORS) caso precise
header("Access-Control-Allow-Origin: *");

$dir = 'uploads/';
$images = [];

// Cria a pasta se não existir
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            // Verifica se é imagem
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $images[] = $dir . $file;
            }
        }
    }
}

// Retorna as imagens ordenadas da mais recente para a mais antiga (baseado no nome do arquivo que tem timestamp)
rsort($images);

$featuredFile = 'featured.json';
if (file_exists($featuredFile)) {
    $featured = json_decode(file_get_contents($featuredFile), true);
    if (is_array($featured)) {
        $otherImages = [];
        $orderedFeatured = [];
        
        foreach ($images as $img) {
            if (!in_array($img, $featured)) {
                $otherImages[] = $img;
            }
        }
        
        foreach ($featured as $f) {
            if (in_array($f, $images)) {
                $orderedFeatured[] = $f;
            }
        }
        
        $images = array_merge($orderedFeatured, $otherImages);
    }
}

echo json_encode($images);
?>
