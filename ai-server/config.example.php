<?php
/**
 * Konfiguration des KI-Dienstes.
 * Kopieren nach config.php und Werte eintragen – config.php und
 * data.sqlite sind gitignored und bleiben nur auf dem Anbieter-Server.
 */
return [
    // Anthropic-API-Key (https://console.anthropic.com) für den Chat.
    'anthropic_key' => '',
    // Claude-Modell für den Assistenten.
    'model' => 'claude-sonnet-5',
    // Schnelleres/günstigeres Modell für einfache Aufgaben (Planung, Hilfe).
    // Leer lassen, um immer 'model' zu verwenden.
    'fast_model' => 'claude-haiku-4-5-20251001',

    // OpenAI-API-Key (https://platform.openai.com) für die Bildgenerierung.
    'openai_key' => '',
    'image_model' => 'gpt-image-1',
    // Fester Token-Preis, der pro generiertem Bild vom Guthaben abgeht.
    'image_token_price' => 25000,

    // Passwort für admin.php (Lizenzen anlegen, Guthaben aufladen).
    'admin_password' => '',

    // Max. Anfragen pro Lizenz und Minute.
    'rate_limit_per_minute' => 20,

    // true = Mock-Modus für lokale Tests: keine echten API-Aufrufe,
    // vorgefertigte Antworten. In Produktion IMMER false.
    'mock' => false,
];
