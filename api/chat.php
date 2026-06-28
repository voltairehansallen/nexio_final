<?php
/**
 * Nexio S.A. — Bridge PHP → Python IA (chat)
 * Appelle le serveur Flask sur le port 5001
 * Si Python n'est pas lancé : répond en mode démo
 */
require_once __DIR__ . '/../config/app.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['reply' => 'Méthode non autorisée.']);
    exit;
}

$body    = file_get_contents('php://input');
$data    = json_decode($body, true) ?: [];
$message = trim($data['message'] ?? '');
$session = session_id() ?: ($data['session_id'] ?? 'anonymous');
$uid     = $_SESSION['user_id'] ?? null;

if (!$message) {
    echo json_encode(['reply' => 'Message vide.']);
    exit;
}

// ── Essaie d'appeler Python Flask ────────────────────────────
$payload = json_encode([
    'message'    => $message,
    'session_id' => $session,
    'id_user'    => $uid,
]);

$ch = curl_init(PYTHON_API_URL . '/chat');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 3,
]);
$response = curl_exec($ch);
$err      = curl_error($ch);
$code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$err && $code === 200) {
    echo $response;
    exit;
}

// ── Mode démo (Python non disponible) ────────────────────────
$pdo = getDB();
try {
    $pdo->prepare("INSERT INTO chat_messages(session_id,id_user,role,contenu) VALUES(:s,:u,'user',:c)")
        ->execute([':s'=>$session,':u'=>$uid,':c'=>$message]);
} catch (PDOException) {}

// Réponses démo basées sur mots-clés
$msg   = mb_strtolower($message);
$demos = [
    ['mots' => ['bonjour','salut','bonsoir','hello'],
     'rep'  => "👋 Bonjour ! Bienvenue chez Nexio S.A. Comment puis-je vous aider ? (produits, commandes, support technique...)"],
    ['mots' => ['laptop','ordinateur','pc','lenovo','hp','dell'],
     'rep'  => "💻 Nous avons d'excellents laptops disponibles : HP EliteBook (85 000 HTG), Dell Inspiron (62 000 HTG), Lenovo ThinkPad (110 000 HTG). Quel est votre budget ?"],
    ['mots' => ['routeur','wifi','réseau','internet','tp-link'],
     'rep'  => "📶 Pour le réseau, nous proposons le TP-Link Archer AX73 WiFi 6 (18 500 HTG) et le switch 8 ports (8 500 HTG). Parfait pour votre bureau !"],
    ['mots' => ['ssd','disque','stockage','kingston','seagate'],
     'rep'  => "💾 En stockage : SSD Kingston 480GB (9 500 HTG) et HDD Seagate 2TB (12 000 HTG). Le SSD accélère considérablement votre PC."],
    ['mots' => ['prix','cout','combien','tarif'],
     'rep'  => "💰 Nos prix varient de 2 500 HTG (clé USB) à 110 000 HTG (ultrabook professionnel). Quel type de produit vous intéresse ?"],
    ['mots' => ['livraison','délai','livrer'],
     'rep'  => "🚚 Livraison à Port-au-Prince sous 24-48h. Nous couvrons la zone métropolitaine. Des frais peuvent s'appliquer selon votre localisation."],
    ['mots' => ['paiement','moncash','visa','natcash'],
     'rep'  => "💳 Nous acceptons : MonCash, NatCash, carte Visa et paiement en espèces à la livraison. Aucun frais supplémentaire !"],
    ['mots' => ['garantie','retour','échange'],
     'rep'  => "🛡️ Garantie de 6 mois à 2 ans selon le produit. Retour accepté dans les 7 jours pour tout défaut de fabrication."],
    ['mots' => ['commande','suivi','statut'],
     'rep'  => "📦 Pour suivre votre commande, connectez-vous à votre compte et consultez la section 'Mes commandes'. Vous pouvez aussi me donner votre numéro de commande."],
    ['mots' => ['merci','thank','parfait','super'],
     'rep'  => "😊 De rien ! N'hésitez pas à revenir si vous avez d'autres questions. Bon shopping chez Nexio S.A. !"],
];

$reply = "🤖 Je comprends votre question concernant \"$message\". Pour une aide personnalisée, contactez-nous à Delmas, Port-au-Prince (Lun-Sam 8h-18h) ou je peux vous renseigner sur nos produits, prix, livraisons et garanties.";
foreach ($demos as $d) {
    foreach ($d['mots'] as $m) {
        if (str_contains($msg, $m)) { $reply = $d['rep']; break 2; }
    }
}

try {
    $pdo->prepare("INSERT INTO chat_messages(session_id,id_user,role,contenu) VALUES(:s,:u,'assistant',:c)")
        ->execute([':s'=>$session,':u'=>$uid,':c'=>$reply]);
} catch (PDOException) {}

echo json_encode(['reply' => $reply, 'status' => 'demo', 'note' => 'Lancez python/main.py pour activer Grok IA']);
