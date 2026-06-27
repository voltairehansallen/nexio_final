<?php
/**
 * Nexio S.A. — API Envoi Multi-canal
 * Déclenche l'envoi Email/WhatsApp/Facebook via le serveur Flask.
 * Accessible uniquement par les administrateurs.
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
header('Content-Type: application/json; charset=utf-8');

// Auth obligatoire
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'Accès refusé']); exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? ($_GET['action'] ?? '');

/**
 * Helper : appel au serveur Flask Python
 */
function flaskPost(string $route, array $payload, int $timeout = 60): array {
    $ch = curl_init("http://127.0.0.1:5001$route");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) return ['error' => true, 'msg' => "Serveur IA non disponible. Lancez python/main.py ($err)"];
    return json_decode($resp, true) ?? ['error' => true, 'msg' => 'Réponse invalide'];
}

function flaskGet(string $route): array {
    $ch = curl_init("http://127.0.0.1:5001$route");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 3]);
    $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) return ['error' => true];
    return json_decode($resp, true) ?? [];
}

switch ($action) {

    /* ─── Envoyer une campagne complète ─────────────────────── */
    case 'envoyer_campagne':
        $id = (int)($body['id_campagne'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'id_campagne requis']); break; }

        // Vérification campagne existe et n'est pas déjà envoyée
        $pdo = getDB();
        $c = $pdo->prepare("SELECT statut,nom FROM campagnes WHERE id_campagne=:i");
        $c->execute([':i' => $id]); $camp = $c->fetch();
        if (!$camp) { echo json_encode(['error' => 'Campagne introuvable']); break; }
        if ($camp['statut'] === 'Envoyée') {
            echo json_encode(['warning' => 'Campagne déjà envoyée', 'nom' => $camp['nom']]); break;
        }

        $result = flaskPost('/envoyer-campagne', ['id_campagne' => $id], 120);
        echo json_encode($result);
        break;

    /* ─── Envoi test (email unique) ─────────────────────────── */
    case 'test_envoi':
        $email   = trim($body['email']   ?? '');
        $message = trim($body['message'] ?? 'Test depuis Nexio S.A. — votre plateforme IA est opérationnelle !');
        $canal   = $body['canal'] ?? 'Email';
        if (!$email) { echo json_encode(['error' => 'Email/destinataire requis']); break; }

        $result = flaskPost('/test-envoi', ['email' => $email, 'message' => $message, 'canal' => $canal], 30);
        echo json_encode($result);
        break;

    /* ─── Générer campagne avec IA puis envoyer ─────────────── */
    case 'generer_et_envoyer':
        $id      = (int)($body['id_campagne'] ?? 0);
        $envoyer = (bool)($body['envoyer'] ?? false);
        if (!$id) { echo json_encode(['error' => 'id_campagne requis']); break; }

        // 1. Charger campagne
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM campagnes WHERE id_campagne=:i");
        $stmt->execute([':i' => $id]); $camp = $stmt->fetch();
        if (!$camp) { echo json_encode(['error' => 'Campagne introuvable']); break; }

        // 2. Générer contenu IA si vide
        if (empty($camp['contenu'])) {
            $gen = flaskPost('/generer-campagne', [
                'nom'     => $camp['nom'],
                'canal'   => $camp['canal'],
                'type'    => $camp['type'],
                'segment' => $camp['segment'] ?? '',
                'id_user' => $camp['id_user_cible'] ?? null,
            ], 60);

            if (!isset($gen['error'])) {
                $pdo->prepare("UPDATE campagnes SET titre_ia=:ti,slogan=:sl,contenu=:co,contenu_email=:ce,contenu_whatsapp=:cw,contenu_facebook=:cf,appel_action=:aa WHERE id_campagne=:i")
                    ->execute([
                        ':ti' => $gen['titre']     ?? $camp['nom'],
                        ':sl' => $gen['slogan']    ?? '',
                        ':co' => $gen['contenu']   ?? '',
                        ':ce' => $gen['email']     ?? '',
                        ':cw' => $gen['whatsapp']  ?? '',
                        ':cf' => $gen['facebook']  ?? '',
                        ':aa' => $gen['appel_action'] ?? '',
                        ':i'  => $id,
                    ]);
            }
        }

        // 3. Envoyer si demandé
        if ($envoyer) {
            $result = flaskPost('/envoyer-campagne', ['id_campagne' => $id], 120);
            echo json_encode(array_merge(['ia_generee' => true], $result));
        } else {
            echo json_encode(['ia_generee' => true, 'statut' => 'Contenu généré, prêt à envoyer', 'campagne' => $id]);
        }
        break;

    /* ─── Statut agents ──────────────────────────────────────── */
    case 'agents_status':
        $result = flaskGet('/agents-status');
        if (empty($result) || isset($result['error'])) {
            echo json_encode([
                'error'     => true,
                'msg'       => 'Serveur IA non disponible. Lancez : cd python && python main.py',
                'grok_ready'=> false,
                'agents'    => [],
            ]);
        } else {
            echo json_encode($result);
        }
        break;

    /* ─── Poster sur Facebook ────────────────────────────────── */
    case 'poster_facebook':
        $message = trim($body['message'] ?? '');
        $lien    = trim($body['lien']    ?? 'http://localhost/nexio_final');
        if (!$message) { echo json_encode(['error' => 'message requis']); break; }

        $result = flaskPost('/test-envoi', [
            'email'   => $lien,  // champ réutilisé pour le lien
            'message' => $message,
            'canal'   => 'Facebook',
        ], 30);
        echo json_encode($result);
        break;

    /* ─── Planifier une campagne ─────────────────────────────── */
    case 'planifier':
        $id         = (int)($body['id_campagne'] ?? 0);
        $date_debut = $body['date_debut'] ?? null;
        $date_fin   = $body['date_fin']   ?? null;
        if (!$id) { echo json_encode(['error' => 'id_campagne requis']); break; }

        $pdo = getDB();
        $pdo->prepare("UPDATE campagnes SET statut='Planifiée',date_debut=:db,date_fin=:df WHERE id_campagne=:i")
            ->execute([':db' => $date_debut, ':df' => $date_fin, ':i' => $id]);
        echo json_encode(['statut' => 'Planifiée', 'campagne' => $id, 'date_debut' => $date_debut, 'date_fin' => $date_fin]);
        break;

    /* ─── Vérifier campagnes terminées (cron) ───────────────── */
    case 'check_expired':
        $pdo = getDB();
        $stmt = $pdo->query("SELECT id_campagne FROM campagnes WHERE statut='En cours' AND date_fin IS NOT NULL AND date_fin < NOW()");
        $expired = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($expired as $cid) {
            $pdo->prepare("UPDATE campagnes SET statut='Terminée' WHERE id_campagne=:i")->execute([':i' => $cid]);
        }
        echo json_encode(['terminées' => count($expired), 'ids' => $expired]);
        break;

    default:
        echo json_encode(['error' => "Action '$action' inconnue", 'actions_disponibles' => [
            'envoyer_campagne', 'test_envoi', 'generer_et_envoyer',
            'agents_status', 'poster_facebook', 'planifier', 'check_expired'
        ]]);
}
