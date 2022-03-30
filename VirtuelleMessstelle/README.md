# Virtuelle Messstelle
Rechnet positive Variablenänderungen nach einstellbaren Regeln mit Veränderungen einer Hauptvariable zusammen und addiert dieses mit dem Wert einer Variable. 

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Auswahl einer geloggten Variable als primären Messstelle 
* Auswahl beliebig vieler Variablen als sekundären Messstellen
* Einstellbar, ob die Veränderungen der sekundären Messtellen zu den Veränderungen der primären Messstellen addiert oder subtrahiert werden sollen

### 2. Voraussetzungen

- IP-Symcon ab Version 5.5

### 3. Software-Installation

* Über den Module Store das 'Virtuelle Messstelle'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: https://github.com/symcon/VirtuelleMessstelle

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Virtuelle Messstelle'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name                 | Beschreibung
-------------------- | ------------------
Primäre Messstelle   | Auswahl einer geloggten Variable
Sekundäre Messstelle | Mehrfachauswahl von Variablen 
Operation            | __Addieren/Subtrahieren__ Option, ob die positive Veränderung der sekundären Messstelle zu der primären Messstelle hinzugefügt oder abgezogen werden soll
Variable             | Auswahl einer geloggte Variable als sekundäre Messstelle 

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name    | Typ   | Beschreibung
------- | ------| ------------
Ergebnis| Float | Errechnetes Ergebnis

### 7. PHP-Befehlsreferenz

`boolean VM_Update(integer $InstanzID, float $PrimaryDelta);`
Die Steigung der sekundären Messstellen wird je nach ausgewählter Option addiert oder subtrahiert. Das Ergebnis wird mit dem übergebenen Wert zusammen auf den Wert der Variable 'Ergebnis' addiert. 

Beispiel:
`VM_Update(12345, 5.3);`
