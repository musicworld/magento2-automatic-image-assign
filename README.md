
# Musicworld_AutomaticImageAssign

Dieses Magento 2 Modul bietet drei Konsolenbefehle, um Produktbilder zu verwalten: automatische Zuweisung von Basis-, Thumbnail- und kleinen Bildern, das Bereinigen von Produkten mit fehlenden Bildern und das Sortieren von Produktbildern, bei denen Videos nach hinten verschoben werden, wenn sie die kleinste Position haben.

## Installation

1. Lege das Modul-Verzeichnis unter `app/code/Musicworld/AutomaticImageAssign` an.
2. Kopiere die Moduldateien in dieses Verzeichnis.
3. Führe die folgenden Befehle aus, um das Modul zu aktivieren und die Abhängigkeiten zu aktualisieren:

   ```bash
   php bin/magento module:enable Musicworld_AutomaticImageAssign
   php bin/magento setup:upgrade
   ```

## Verwendung

### 1. `musicworld:assign-images`

Dieser Befehl weist Produkte, die noch kein Basis-, Thumbnail- oder kleines Bild haben, das erste verfügbare Bild aus der Mediengalerie zu.

```bash
php bin/magento musicworld:assign-images [--save]
```

- **Optionen**:
    - `--save`: Speichert das Produkt nach der Zuweisung der Bilder. Ohne diese Option wird der Befehl im "Dry Run"-Modus ausgeführt und zeigt nur die Änderungen an.

### 2. `musicworld:clean-missing-images`

Dieser Befehl bereinigt Produkte, bei denen eines oder mehrere der Bilder (Basis-, Thumbnail- oder kleines Bild) fehlen, und setzt die fehlenden Bilder auf `no_selection`.

```bash
php bin/magento musicworld:clean-missing-images [--save]
```

- **Optionen**:
    - `--save`: Speichert das Produkt nach der Bereinigung der fehlenden Bilder. Ohne diese Option wird der Befehl im "Dry Run"-Modus ausgeführt und zeigt nur die geplanten Änderungen an.

### 3. `musicworld:sort-product-images`

Dieser Befehl sortiert die Bilder eines Produkts und verschiebt Videos ans Ende der Reihenfolge, wenn sie die kleinste Positionsnummer haben.

```bash
php bin/magento musicworld:sort-product-images
```

- Es gibt keine zusätzlichen Optionen für diesen Befehl. Produkte mit Videos werden bearbeitet, und wenn Änderungen erforderlich sind, wird das Produkt nach der Aktualisierung gespeichert.

## Beispiel

- Um Bilder zu Produkten ohne Zuweisung zuweisen und die Änderungen direkt zu speichern, führe folgendes aus:

  ```bash
  php bin/magento musicworld:assign-images --save
  ```

- Um Produkte mit fehlenden Bildern zu bereinigen, ohne die Änderungen zu speichern:

  ```bash
  php bin/magento musicworld:clean-missing-images
  ```