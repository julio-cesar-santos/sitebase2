<?php
session_start();
require_once 'conexao.php';
require_once 'auth.php';

// Comentado para permitir que o script sirva imagens diretamente
// header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // RF02: O sistema deve exibir a lista de produtos disponíveis para o cliente com seus respectivos detalhes.
        if (isset($_GET['id'])) {
            // REMOVIDO: 'descricao' na seleção direta, embora o campo exista no BD
            $stmt = $pdo->prepare("SELECT id, nome, preco, estoque, imagem_tipo FROM produtos WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $product = $stmt->fetch();
            if ($product) {
                if (isset($_GET['get_image']) && $_GET['get_image'] == 'true') {
                    $stmt_img = $pdo->prepare("SELECT imagem, imagem_tipo FROM produtos WHERE id = ?");
                    $stmt_img->execute([$_GET['id']]);
                    $imageData = $stmt_img->fetch();
                    if ($imageData && !empty($imageData['imagem'])) {
                        header("Content-Type: " . $imageData['imagem_tipo']);
                        echo $imageData['imagem'];
                        exit();
                    } else {
                        header("Content-Type: image/png");
                        readfile("img/placeholder.png");
                        exit();
                    }
                } else {
                    echo json_encode($product);
                }
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Produto não encontrado.']);
            }
        } else {
            // REMOVIDO: 'descricao' na seleção para listagem
            $stmt = $pdo->query("SELECT id, nome, preco, estoque, imagem_tipo FROM produtos");
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        // RF01: O sistema deve permitir o cadastro, edição e exclusão de produtos pelo administrador (dono da loja).
        // RF07: O sistema deve permitir a adição de novos produtos pelo administrador.
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['message' => 'Acesso negado. Apenas administradores podem adicionar produtos.']);
            exit();
        }

        $nome = $_POST['nome'] ?? '';
        // REMOVIDO: Descrição não é mais esperada do POST, será null ou string vazia por padrão
        // $descricao = $_POST['descricao'] ?? null; 
        $preco = $_POST['preco'] ?? 0;
        $estoque = $_POST['estoque'] ?? 0;

        $imagem = null;
        $imagem_tipo = null;

        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == UPLOAD_ERR_OK) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($_FILES['imagem']['tmp_name']);

            if (!in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Tipo de arquivo não permitido. Apenas JPG, PNG, GIF.']);
                exit();
            }

            $imagem = file_get_contents($_FILES['imagem']['tmp_name']);
            $imagem_tipo = $mime_type;

            if (strlen($imagem) > 16 * 1024 * 1024) {
                 http_response_code(400);
                 echo json_encode(['message' => 'Imagem muito grande. Máximo 16MB.']);
                 exit();
            }

        } else if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] != UPLOAD_ERR_NO_FILE) {
            http_response_code(400);
            echo json_encode(['message' => 'Erro no upload da imagem: ' . $_FILES['imagem']['error']]);
            exit();
        }

        if (empty($nome) || empty($preco)) {
            http_response_code(400);
            echo json_encode(['message' => 'Nome e preço são obrigatórios.']);
            exit();
        }

        try {
            // ALTERADO: A descrição foi removida da query INSERT. O banco usará o DEFAULT (NULL).
            $stmt = $pdo->prepare("INSERT INTO produtos (nome, preco, imagem, imagem_tipo, estoque) VALUES (?, ?, ?, ?, ?)");
            $stmt->bindParam(1, $nome);
            $stmt->bindParam(2, $preco);
            $stmt->bindParam(3, $imagem, PDO::PARAM_LOB);
            $stmt->bindParam(4, $imagem_tipo);
            $stmt->bindParam(5, $estoque);
            $stmt->execute();
            echo json_encode(['message' => 'Produto adicionado com sucesso!', 'id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao adicionar produto: ' . $e->getMessage()]);
        }
        break;

    case 'PUT':
        // RF01: O sistema deve permitir o cadastro, edição e exclusão de produtos pelo administrador (dono da loja).
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['message' => 'Acesso negado. Apenas administradores podem editar produtos.']);
            exit();
        }

        $id = $_POST['id'] ?? 0;
        $nome = $_POST['nome'] ?? '';
        // REMOVIDO: Descrição não é mais esperada do POST para atualização
        // $descricao = $_POST['descricao'] ?? null;
        $preco = $_POST['preco'] ?? 0;
        $estoque = $_POST['estoque'] ?? 0;

        $imagem = null;
        $imagem_tipo = null;
        $update_image = false;

        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == UPLOAD_ERR_OK) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($_FILES['imagem']['tmp_name']);

            if (!in_array($mime_type, ['image/jpeg', 'image/png', 'image/gif'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Tipo de arquivo não permitido. Apenas JPG, PNG, GIF.']);
                exit();
            }

            $imagem = file_get_contents($_FILES['imagem']['tmp_name']);
            $imagem_tipo = $mime_type;
            $update_image = true;

             if (strlen($imagem) > 16 * 1024 * 1024) {
                 http_response_code(400);
                 echo json_encode(['message' => 'Imagem muito grande. Máximo 16MB.']);
                 exit();
            }

        } else if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] != UPLOAD_ERR_NO_FILE) {
             http_response_code(400);
             echo json_encode(['message' => 'Erro no upload da imagem: ' . $_FILES['imagem']['error']]);
             exit();
        }

        if (empty($id) || empty($nome) || empty($preco)) {
            http_response_code(400);
            echo json_encode(['message' => 'ID, nome e preço são obrigatórios para edição.']);
            exit();
        }

        try {
            if ($update_image) {
                // ALTERADO: Descrição removida da query UPDATE
                $stmt = $pdo->prepare("UPDATE produtos SET nome = ?, preco = ?, imagem = ?, imagem_tipo = ?, estoque = ? WHERE id = ?");
                $stmt->bindParam(1, $nome);
                $stmt->bindParam(2, $preco);
                $stmt->bindParam(3, $imagem, PDO::PARAM_LOB);
                $stmt->bindParam(4, $imagem_tipo);
                $stmt->bindParam(5, $estoque);
                $stmt->bindParam(6, $id);
            } else {
                // ALTERADO: Descrição removida da query UPDATE (sem imagem)
                $stmt = $pdo->prepare("UPDATE produtos SET nome = ?, preco = ?, estoque = ? WHERE id = ?");
                $stmt->bindParam(1, $nome);
                $stmt->bindParam(2, $preco);
                $stmt->bindParam(3, $estoque);
                $stmt->bindParam(4, $id);
            }
            $stmt->execute();
            echo json_encode(['message' => 'Produto atualizado com sucesso!']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao atualizar produto: ' . $e->getMessage()]);
        }
        break;


    case 'DELETE':
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['message' => 'Acesso negado. Apenas administradores podem excluir produtos.']);
            exit();
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;

        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['message' => 'ID do produto é obrigatório para exclusão.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['message' => 'Produto excluído com sucesso!']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao excluir produto: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Método não permitido.']);
        break;
}
?>