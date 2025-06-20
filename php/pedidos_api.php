<?php
session_start();
require_once 'conexao.php';
require_once 'auth.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // RF05: O sistema deve permitir ao administrador visualizar relatórios de faturamento.
        // Permite listar todos os pedidos (admin) ou pedidos do usuário logado (cliente)
        if (isAdmin()) {
            if (isset($_GET['faturamento'])) {
                // RF05: Relatório de faturamento
                $stmt = $pdo->query("SELECT DATE(data_pedido) as data, SUM(total) as faturamento_diario FROM pedidos GROUP BY DATE(data_pedido) ORDER BY data DESC");
                echo json_encode($stmt->fetchAll());
            } else {
                $stmt = $pdo->query("SELECT p.*, u.nome as cliente_nome FROM pedidos p JOIN usuarios u ON p.usuario_id = u.id ORDER BY data_pedido DESC");
                echo json_encode($stmt->fetchAll());
            }
        } else if (isAuthenticated()) {
            $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE usuario_id = ? ORDER BY data_pedido DESC");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode($stmt->fetchAll());
        } else {
            http_response_code(401);
            echo json_encode(['message' => 'Não autorizado. Faça login para ver seus pedidos.']);
        }
        break;

    case 'POST':
        // RF03: O cliente deve poder adicionar produtos ao carrinho e realizar pedidos.
        // RF04: O sistema deve permitir ao cliente selecionar entre retirada no local ou entrega.
        if (!isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['message' => 'Não autorizado. Faça login para realizar um pedido.']);
            exit();
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $carrinho = $data['carrinho'] ?? []; // Array de {produto_id, quantidade}
        
        // ALTERADO: tipo_entrega agora é sempre 'retirada'
        $tipo_entrega = 'retirada'; 

        if (empty($carrinho)) {
            http_response_code(400);
            echo json_encode(['message' => 'Dados do pedido inválidos. O carrinho está vazio.']);
            exit();
        }

        $pdo->beginTransaction();
        try {
            $total_pedido = 0;
            $itens_para_inserir = [];

            foreach ($carrinho as $item) {
                $produto_id = $item['produto_id'];
                $quantidade = $item['quantidade'];

                $stmt = $pdo->prepare("SELECT preco, estoque FROM produtos WHERE id = ?");
                $stmt->execute([$produto_id]);
                $produto = $stmt->fetch();

                if (!$produto || $produto['estoque'] < $quantidade) {
                    $pdo->rollBack();
                    http_response_code(400);
                    echo json_encode(['message' => 'Estoque insuficiente para o produto ID ' . $produto_id]);
                    exit();
                }

                $total_item = $produto['preco'] * $quantidade;
                $total_pedido += $total_item;
                $itens_para_inserir[] = [
                    'produto_id' => $produto_id,
                    'quantidade' => $quantidade,
                    'preco_unitario' => $produto['preco']
                ];

                // Atualiza o estoque do produto
                $stmt = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
                $stmt->execute([$quantidade, $produto_id]);
            }

            $stmt = $pdo->prepare("INSERT INTO pedidos (usuario_id, tipo_entrega, total) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $tipo_entrega, $total_pedido]);
            $pedido_id = $pdo->lastInsertId();

            foreach ($itens_para_inserir as $item) {
                $stmt = $pdo->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)");
                $stmt->execute([$pedido_id, $item['produto_id'], $item['quantidade'], $item['preco_unitario']]);
            }

            $pdo->commit();
            // RF06: O sistema deve notificar o administrador sobre novos pedidos realizados.
            error_log("Novo pedido realizado! ID do Pedido: " . $pedido_id . " por usuário ID: " . $_SESSION['user_id']);

            echo json_encode(['message' => 'Pedido realizado com sucesso!', 'pedido_id' => $pedido_id]);

        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao realizar pedido: ' . $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Permitir que o administrador mude o status do pedido
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['message' => 'Acesso negado. Apenas administradores podem atualizar pedidos.']);
            exit();
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $pedido_id = $data['pedido_id'] ?? 0;
        $status = $data['status'] ?? '';

        if (empty($pedido_id) || !in_array($status, ['pendente', 'processando', 'concluido', 'cancelado'])) {
            http_response_code(400);
            echo json_encode(['message' => 'ID do pedido e status válidos são obrigatórios para atualização.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
            $stmt->execute([$status, $pedido_id]);
            echo json_encode(['message' => 'Status do pedido atualizado com sucesso!']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['message' => 'Erro ao atualizar status do pedido: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Método não permitido.']);
        break;
}

?>