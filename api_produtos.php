<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

$produtosFile = 'produtos.json';
if (file_exists($produtosFile)) {
    echo file_get_contents($produtosFile);
} else {
    echo json_encode([]);
}
?>
