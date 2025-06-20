document.addEventListener('DOMContentLoaded', function() {
    const navLogin = document.getElementById('nav-login');
    const navCadastro = document.getElementById('nav-cadastro');
    const navConta = document.getElementById('nav-conta');
    const navAdmin = document.getElementById('nav-admin');
    const navLogout = document.getElementById('nav-logout');
    const cartLink = document.getElementById('cart-link');
    const cartCountDisplay = document.querySelector('.cart-count-display');

    // Função para atualizar o contador do carrinho na UI
    function updateCartCount() {
        let carrinho = JSON.parse(localStorage.getItem('carrinho')) || [];
        const totalItems = carrinho.reduce((sum, item) => sum + item.quantidade, 0);
        if (cartCountDisplay) { // Verifica se o elemento existe
            if (totalItems > 0) {
                cartCountDisplay.textContent = totalItems;
                cartCountDisplay.style.display = 'inline-block';
            } else {
                cartCountDisplay.style.display = 'none';
            }
        }
    }

    // Função para verificar o status de login e admin
    async function checkAuthStatus() {
        try {
            const response = await fetch('php/auth_status.php'); // Novo arquivo para verificar o status de autenticação
            const data = await response.json();

            if (data.isAuthenticated) {
                navLogin.style.display = 'none';
                navCadastro.style.display = 'none';
                navConta.style.display = 'inline-block';
                navLogout.style.display = 'inline-block';
                cartLink.style.display = 'inline-block'; // Mostra o carrinho para usuários logados

                if (data.isAdmin) {
                    navAdmin.style.display = 'inline-block';
                } else {
                    navAdmin.style.display = 'none';
                }
            } else {
                navLogin.style.display = 'inline-block';
                navCadastro.style.display = 'inline-block';
                navConta.style.display = 'none';
                navAdmin.style.display = 'none';
                navLogout.style.display = 'none';
                cartLink.style.display = 'none'; // Esconde o carrinho para usuários não logados
            }
        } catch (error) {
            console.error('Erro ao verificar status de autenticação:', error);
            // Em caso de erro, assumir não logado para evitar acesso indevido
            navLogin.style.display = 'inline-block';
            navCadastro.style.display = 'inline-block';
            navConta.style.display = 'none';
            navAdmin.style.display = 'none';
            navLogout.style.display = 'none';
            cartLink.style.display = 'none';
        }
    }

    // Lógica para o botão de logout
    if (navLogout) {
        navLogout.addEventListener('click', async function(event) {
            event.preventDefault();
            try {
                const response = await fetch('php/logout.php');
                if (response.ok) {
                    alert('Sessão encerrada com sucesso!');
                    localStorage.removeItem('carrinho'); // Limpa o carrinho ao fazer logout
                    window.location.href = 'index.html';
                } else {
                    alert('Erro ao fazer logout.');
                }
            } catch (error) {
                console.error('Erro de logout:', error);
                alert('Ocorreu um erro ao tentar sair.');
            }
        });
    }

    // Define o ano atual no footer
    const anoAtualSpan = document.getElementById('anoAtual');
    if (anoAtualSpan) {
        anoAtualSpan.textContent = new Date().getFullYear();
    }

    // Chama as funções ao carregar a página
    checkAuthStatus();
    updateCartCount();
});