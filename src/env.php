<?php
function loadEnvVars($envPath) {
    if (file_exists($envPath)) {
        foreach (file($envPath) as $line) {
            if (preg_match('/^([A-Z0-9_]+)=(.*)$/', trim($line), $matches)) {
                $name = $matches[1];
                $value = trim($matches[2], '"');
                putenv("$name=$value");
            }
        }
    }
}
