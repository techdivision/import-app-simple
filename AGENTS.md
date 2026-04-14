# AGENTS.md - import-app-simple

## Zweck & Verantwortung

Das `import-app-simple` Modul bietet eine **einfache Application-Implementierung** für das Pacemaker Import-System. Es ist ein **Tier 3 Modul** und dient als Basis für CLI-Anwendungen.

**Hauptverantwortung:**
- Simple Application Implementation
- Basis für CLI-Anwendungen
- Orchestrierung von Import-Prozessen
- Fehlerbehandlung und Reporting

## Architektur & Design Patterns

### Kern-Klassen
- **Simple**: Haupt-Application-Klasse
- **ApplicationInterface**: Basis-Interface für Anwendungen
- **ProcessorInterface**: Basis-Interface für Prozessoren

### Verwendete Patterns
- **Application Pattern**: Zentrale Anwendungs-Logik
- **Processor Pattern**: Für Prozess-Orchestrierung
- **Factory Pattern**: Für Object-Erstellung

## Abhängigkeiten

### Externe Pakete
- **Keine** - Nur Application-Implementierungen

### TechDivision Dependencies
- **import** ^18.0.0 - Core Framework

### Abhängig von diesem Modul (2 Reverse Dependencies)
1. **import-cli** - CLI nutzt Simple Application
2. **import-cli-simple** - Master CLI nutzt Simple Application

## Wichtige Entry Points

### Application Klassen
```php
// Simple Application
Simple::run(): void
Simple::process($configuration): void

// Application Interface
ApplicationInterface::run(): void
ApplicationInterface::process($configuration): void
```

### Verwendungsbeispiel
```php
// In CLI
$application = new Simple($configuration);
$application->run();
```

## Events & Extension Points

**Keine Events** - Tier 3 Implementierungs-Modul

## Hints für KI-Agenten

### Wichtig zu verstehen
1. **Tier 3 Modul**: Basis für CLI-Anwendungen
2. **Simple Implementation**: Einfache, fokussierte Implementierung
3. **2 Dependents**: Basis für CLI-Module
4. **Single-Threaded**: Für Single-Threaded Imports

### Bei Änderungen
- **Implementierungs-Details**: Können geändert werden
- **Backward Compatibility**: Alte Anwendungen sollten noch funktionieren
- **CLI-Kompatibilität**: Beachte CLI-Integration

## Häufige Use Cases

### CLI-Integration Beispiele
```php
// In import-cli Module
$application = new Simple($configuration);
$application->run();
// Orchestriert vollständigen Import-Prozess
```

### Szenarien
1. **CLI Command Integration**: Commands nutzen Simple für Ausführung
2. **Batch-Imports**: Automatisierte Nightly-Imports
3. **Cron-Job Execution**: Cron-Jobs nutzen Simple für Process-Execution

## Performance-Überlegungen

- **Startup-Overhead**: ~100-200ms für Application-Init und DI-Setup
- **Single-Process**: Keine Parallelisierung - sequenziell linear
- **Memory-Baseline**: ~30-50MB base Memory für Framework
- **Importer-Größe**: 10k Products ~2-3 Minuten, 100k ~20-30 Minuten
- **Bottleneck**: Usually DB-Operationen, nicht Application-Layer

## Verwandte Module

- **import-cli**: Nutzt Simple für CLI-Commands
- **import-cli-simple**: Master CLI nutzt Simple Application
- **import-app-simple** ← **diese Datei** (Framework!)
- **import**: Core Framework

## Troubleshooting & FAQ

**Q: Import läuft extrem langsam**
- A: Simple selbst ist selten Bottleneck. Prüfe: DB-Indices, Network-Latenz, DBAL-Performance.

**Q: Application crashed mitten im Import**
- A: Single-Process bedeutet: kein Restart möglich. Prüfe Memory-Limit: `php.ini memory_limit`

**Q: Wie kann ich Multi-Threaded verwenden?**
- A: Simple unterstützt das nicht. Nutze `pacemaker/import-app-async` oder ähnlich für Parallelisierung.

## Bekannte Einschränkungen

- **Single-Threaded**: Nicht für Multi-Threaded Imports
- **Keine Konfiguration**: Konfiguration erfolgt in CLI-Modulen
- **Einfache Implementierung**: Keine erweiterten Features

## Zusammenfassung

`import-app-simple` ist ein **Tier 3 Modul**, das eine einfache Application-Implementierung für das Pacemaker-System bietet. Es ist die Basis für CLI-Anwendungen und dient als Einstiegspunkt für Import-Prozesse.

**Für Agenten:** Verstehe dieses Modul als **Simple Application Implementation** für CLI-Integration.
