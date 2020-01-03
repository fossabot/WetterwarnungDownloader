<?php

declare(strict_types=1);

/*
 *  WarnParser für neuthardwetter.de by Jens Dutzi
 *
 *  @package    blog404de\WetterWarnung
 *  @author     Jens Dutzi <jens.dutzi@tf-network.de>
 *  @copyright  Copyright (c) 2012-2019 Jens Dutzi (http://www.neuthardwetter.de)
 *  @license    https://github.com/Blog404DE/WetterwarnungDownloader/blob/master/LICENSE.md
 *  @version    v3.1.4
 *  @link       https://github.com/Blog404DE/WetterwarnungDownloader
 */

namespace blog404de\WetterWarnung\Save;

use blog404de\Standard;
use blog404de\WetterWarnung\Reader\Parser;
use Exception;

/**
 * Klasse für das speichern der aktuell gültigen Wetterwarnungen in eine einzelne Datei.
 */
class SaveToFile extends Parser {
    /** @var Standard\Toolbox Instanz der generischen Toolbox-Klasse */
    private $toolbox;

    /** @var array Array mit den Wetterwarnungen des letzten Laufs */
    private $lastWetterWarnungen;

    /**
     * SaveToFile constructor.
     *
     * @throws Exception
     */
    public function __construct() {
        $this->toolbox = new Standard\Toolbox();
    }

    /**
     * Speichern der Wetterwarnung in JSON Datei.
     *
     * @param array  $wetterWarnungen Array mit allen gültigen Wetterwarnungen
     * @param string $localJsonFile   Pfad und Dateiname in welche die Wetterwarnungen gespeichert werden sollen
     *
     * @throws Exception
     *
     * @return bool
     */
    public function saveFile(array $wetterWarnungen, string $localJsonFile) {
        try {
            // Prüfe ob Zugriff auf json-Datei existiert
            if (empty($localJsonFile) || !is_writable($localJsonFile)) {
                throw new Exception(
                    'Es ist kein Pfad zu der lokalen JSON-Datei mit den Wetterwarnungen vorhanden oder es ' .
                    'besteht kein Schreibzugriff auf die Datei (Pfad: ' . $localJsonFile . ')'
                );
            }

            // Wetterwarnungen aufbereiten (Key entfernen)
            $wetterWarnungen = ['anzahl' => \count($wetterWarnungen),
                'wetterwarnungen' => array_values($wetterWarnungen), ];

            // Wandle in JSON um
            echo "\t* Konvertiere Wetterwarnungen in JSON-Daten" . PHP_EOL;
            $jsonWetterWarnung = @json_encode($wetterWarnungen, JSON_PRETTY_PRINT);
            if (json_last_error() > 0) {
                throw new Exception(
                    'Fehler während der JSON Kodierung der Wetter-Warnungen ' .
                    '(Fehler: ' . $this->toolbox->getJsonErrorMessage(json_last_error()) . ')'
                );
            }

            // Prüfe ob eine neue Datei gespeichert werden muss
            if ($this->shouldSaveFile($jsonWetterWarnung, $localJsonFile)) {
                echo "\t* Änderung bei den Wetterwarnungen gefunden - speichere neue Wetterwarnung" . PHP_EOL;
                $saveJson = file_put_contents($localJsonFile, $jsonWetterWarnung);
                if (!$saveJson) {
                    throw new Exception(
                        'Fehler beim speichern der verarbeiteten Wetterwarnungen (' . $localJsonFile . ')'
                    );
                }

                $fileupdated = true;
            } else {
                echo "\t* Keine Änderung bei den Wetterwarnungen vorhanden - kein speichern notwendig" . PHP_EOL;
                $fileupdated = false;
            }

            return $fileupdated;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Prüfe ob aktuelle Wetterwarnung bereits in der letzten WetterWarnung Datei vorhanden war.
     *
     * @param array $parsedWarnInfo Aktuelle Wetterwarnung
     *
     * @throws Exception
     */
    public function didWetterWarnungExist(array $parsedWarnInfo): bool {
        try {
            $hashExists = false;

            // Prüfe ob Hash der Wetterwarnung vorkommt in der letzten Wetterwarnung
            if (!\array_key_exists('anzahl', $this->lastWetterWarnungen) ||
                !\array_key_exists('wetterwarnungen', $this->lastWetterWarnungen)
            ) {
                throw new Exception(
                    'Inhalt der zuletzt gespeicherten Wetterwarnung beinhaltet' .
                    'ein unbekanntes Format.'
                );
            }

            // Hatte die Datei ein Inhalt?
            if (\count($this->lastWetterWarnungen['wetterwarnungen']) > 0) {
                // Prüfe ob der identifier beim letzten Durchgang bereits verarbeitet wurde
                if (0 === $this->lastWetterWarnungen['anzahl']) {
                    $hashExists = false;
                } else {
                    foreach ($this->lastWetterWarnungen['wetterwarnungen'] as $lastWetterWarnung) {
                        if ($parsedWarnInfo['identifier'] === $lastWetterWarnung['identifier']) {
                            $hashExists = true;
                        }
                    }
                }
            } else {
                // Datei ist leer
                $hashExists = false;
            }

            return $hashExists;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Methode zum laden der letzten Wetterwarnungen aus der Json Datei für späteren Vergleich.
     *
     * @param string $localJsonFile Pfad zur lokalen JSON Datei mit den aktuellen Wetterwarnungen
     *
     * @throws Exception
     */
    final protected function loadLastWetterWarnungen(string $localJsonFile) {
        try {
            // Nehme zuerst die bisherigen Wetterwarnungen in den Speicher
            echo PHP_EOL . '*** Lade zuerst den bisherigen Stand der WetterWarnungen:' . PHP_EOL;

            // Prüfe ob schon eine Warnung verarbeitet wurde
            if (file_exists($localJsonFile)) {
                // Öffne letzte Warnung
                $lastWarnContent = @file_get_contents($localJsonFile);
                if (false === $lastWarnContent) {
                    throw new Exception(
                        'Fehler beim lesen der bisher vorhandenen Wetterwarnungen ' .
                        'zur Prüfung ob eine Veränderung stattfand.'
                    );
                }

                // Dekodiere letzte Wetterwarnung
                if (!empty($lastWarnContent)) {
                    // Inhalt vorhanden -> prüfen
                    $this->lastWetterWarnungen = json_decode($lastWarnContent, true);
                    if (json_last_error()) {
                        throw new Exception(
                            'Fehler beim interpretieren der zuletzt gespeicherten Wetterwarnungen ' .
                            'zur Prüfung ob eine Veränderung stattfand (' .
                            $this->toolbox->getJsonErrorMessage(json_last_error()) . ')'
                        );
                    }
                } else {
                    $this->lastWetterWarnungen = ['anzahl' => 0, 'wetterwarnungen' => []];
                }
            } else {
                $this->lastWetterWarnungen = ['anzahl' => 0, 'wetterwarnungen' => []];
            }

            echo '-> Anzahl der geladenen Wetterwarnungen: ' .
                \count($this->lastWetterWarnungen['wetterwarnungen']) .
                PHP_EOL;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }

    /**
     * Prüfe ob Datei neu gespeichert werden muss oder nicht ...
     *
     * @param string $jsonWetterWarnung In JSON-Format konvertierte Liste mit Wetterwarnungen
     * @param string $localJsonFile     Pfad und Dateiname in welche die Wetterwarnungen gespeichert werden sollen
     *
     * @throws Exception
     */
    private function shouldSaveFile(string $jsonWetterWarnung, string $localJsonFile): bool {
        try {
            // Ermittle MD5-Hashes der bisherigen und ehemaligen Wetterwarnungen
            echo "\t* Ermittle MD5-Hashs der bisherigen Wetterwarnung und " .
                'der neuen Wetterwarnung um Änderungen festzustellen' . PHP_EOL
            ;
            $md5hashes = [];
            $md5hashes['new'] = @md5($jsonWetterWarnung);
            if (empty($md5hashes['new'] || false === $md5hashes['new'])) {
                throw new Exception('Fehler beim erzeugen des MD5-Hashs der neuen Wetterwarnungen');
            }

            $md5hashes['old'] = @md5_file($localJsonFile);
            if (empty($md5hashes['old'] || false === $md5hashes['old'])) {
                throw new Exception('Fehler beim erzeugen des MD5-Hashs der bisherigen Wetterwarnungen');
            }

            echo "\t\t-> MD5-Hashs der bisherigen Wetterwarnungen:\t" . $md5hashes['old'] . PHP_EOL;
            echo "\t\t-> MD5-Hashs der neuen Wetterwarnungen:\t\t" . $md5hashes['new'] . PHP_EOL;

            // Gab es eine Änderung?
            if ($md5hashes['old'] === $md5hashes['new']) {
                // Datei hat keine Veränderung -> nicht speichern
                return false;
            }

            return true;
        } catch (Exception $e) {
            // Fehler an Hauptklasse weitergeben
            throw $e;
        }
    }
}
