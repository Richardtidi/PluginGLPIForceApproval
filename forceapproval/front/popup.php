<?php
/**
 * Plugin forceapproval - Página de bloqueio
 */
include ('../../../inc/includes.php');
$target_url = isset($_SESSION['forceapproval_redirect_url'])
    ? $_SESSION['forceapproval_redirect_url']
    : '../../../front/ticket.php';
unset($_SESSION['forceapproval_redirect_url']);
echo "<!DOCTYPE html>
<html lang='pt-br'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale-1.0'>
    <title>Force Approval - Aprovação Obrigatória</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: linear-gradient(135deg, #f4f5f8ff 0%, #f6f8ff 100%); height: 100vh; display: flex; justify-content: center; align-items: center; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); text-align: center; max-width: 500px; width: 100%; }
        h1 { color: #ff4757; margin-bottom: 20px; }
        p { color: #2f3542; margin-bottom: 15px; }
        .btn { background: #ff4757; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; margin: 10px; }
        .btn:hover { background: #ff6b81; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>⚠️ ATENÇÃO - PENDÊNCIAS ENCONTRADAS</h1>
        <p>Você possui chamados que necessitam de sua aprovação e/ou avaliação.</p>
        <p>Para continuar utilizando o sistema, é <strong>OBRIGATÓRIO</strong> resolver as pendências.</p>
        <button class='btn' onclick='resolvePendency()'>✅ Resolver Pendências</button>
        <button class='btn' onclick='logout()'>🚪 Sair do Sistema</button>
    </div>
    <script>
        function resolvePendency() {
            window.location.href = " . json_encode($target_url) . ";
        }
        function logout() {
            window.location.href = '../../../front/logout.php';
        }
    </script>
</body>
</html>";
?>