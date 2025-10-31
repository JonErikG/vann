# Fiskedagbok WordPress Plugin

Et WordPress plugin for registrerte brukere til å føre fiskedagbok og registrere sine fangster.

## Funksjoner

- ✅ Registrering av fangster for innloggede brukere
- ✅ Visning av brukerens egne fangster
- ✅ Redigering og sletting av egne fangster
- ✅ **Søk etter fangster basert på navn** - finn eksisterende fangster registrert med ditt navn
- ✅ **Klaim fangster** - legg til eksisterende fangster i din personlige fiskedagbok
- ✅ **Detaljert fangstvisning** - utvidet informasjon om hver fangst
- ✅ **Værintegrasjon** - automatisk henting av værdata (simulert data)
- ✅ **CSV Arkiv-system** - lagring av alle CSV-fangster før klaiming
- ✅ **Admin CSV import** - import hele CSV-filer til arkiv for senere søk
- ✅ **Admin søk og klaim** - administratorer kan søke og tildele fangster til brukere
- ✅ Admin oversikt over alle fangster
- ✅ Import av CSV data med automatisk brukerkobling
- ✅ Shortcodes for enkel integrering i sider/innlegg
- ✅ Responsivt design for mobil og desktop

## Installasjon

1. Last ned eller klon dette repositoriet til din WordPress `wp-content/plugins/` mappe
2. Aktiver pluginet i WordPress admin under "Plugins"
3. Pluginet vil automatisk opprette nødvendige database tabeller

### Mappestruktur
```
fiskedagbok/
├── fiskedagbok.php (hovedfil)
├── admin/
│   ├── admin-page.php
│   └── import-page.php
├── templates/
│   ├── catch-form.php
│   └── catch-list.php
├── assets/
│   ├── css/
│   │   ├── frontend.css
│   │   └── admin.css
│   └── js/
│       ├── frontend.js
│       └── admin.js
└── README.md
```

## Bruk

### For Brukere

#### Registrering av fangst
Bruk shortcode `[fiskedagbok_form]` på en side for å vise registreringsskjema:

```
[fiskedagbok_form]
```

#### Visning av fangster
Bruk shortcode `[fiskedagbok_list]` for å vise brukerens fangster:

```
[fiskedagbok_list]
```

#### Søk etter eksisterende fangster
Bruk shortcode `[fiskedagbok_search]` for å søke etter fangster basert på navn:

```
[fiskedagbok_search]
```

#### Kombinert visning
Du kan bruke alle shortcodes på samme side:

```
[fiskedagbok_form]
[fiskedagbok_search]
[fiskedagbok_list]
```

#### For Administratorer

#### Admin Meny
Pluginet legger til en "Fiskedagbok" meny i WordPress admin med:
- **Alle Fangster**: Oversikt over alle registrerte fangster
- **Import CSV**: Importer eksisterende fangstdata direkte til brukere
- **CSV Arkiv**: Import hele CSV-filer til arkiv for senere klaiming
- **Søk og Klaim**: Søk i arkivet og tildel fangster til riktige brukere

#### CSV Arkiv Workflow
1. **Import til arkiv**: Last opp hele CSV-filen til arkiv-tabellen
2. **Søk og tildel**: Bruk admin-søket for å finne og tildele fangster
3. **Bruker-søk**: Brukere kan selv søke og klaime sine fangster
4. **Duplikatkontroll**: Samme fangst importeres kun én gang

#### CSV Import vs CSV Arkiv
- **Import CSV**: Importerer direkte til brukernes fiskedagbøker (krever eksakt navn-matching)
- **CSV Arkiv**: Lagrer alle fangster i arkiv for fleksibel søk og klaiming senere

#### CSV Import
1. Gå til "Fiskedagbok" > "Import CSV" i admin
2. Last opp en CSV-fil med riktig format
3. Pluginet vil automatisk koble fangster til brukere basert på `fisher_name` feltet

### CSV Format

CSV-filen må inneholde følgende kolonner:

| Kolonne | Beskrivelse | Påkrevet |
|---------|-------------|----------|
| catch_id | Unik ID for fangst | Nei |
| date | Dato (YYYY-MM-DD) | Ja |
| time_of_day | Tid (HH:MM) | Nei |
| week | Ukenummer | Nei |
| river_id | Elv ID | Nei |
| river_name | Elvenavn | Anbefalt |
| beat_id | Beat ID | Nei |
| beat_name | Beat navn | Nei |
| fishing_spot | Fiskeplass | Nei |
| fish_type | Fisketype | Ja |
| equipment | Utstyr | Nei |
| weight_kg | Vekt i kg | Nei |
| length_cm | Lengde i cm | Nei |
| released | Sluppet (True/False) | Nei |
| sex | Kjønn (male/female/unknown) | Nei |
| boat | Båt | Nei |
| fisher_name | Fiskerens navn | Anbefalt |
| created_at | Opprettet dato | Nei |
| updated_at | Oppdatert dato | Nei |
| platform_reported_from | Plattform | Nei |

## Database Schema

Pluginet oppretter følgende tabeller:

### wp_fiskedagbok_catches (Hovedtabell)
Lagrer alle aktive fangster som tilhører brukere.

### wp_fiskedagbok_csv_archive (Arkiv-tabell)
Lagrer alle importerte CSV-fangster før de blir clamet av brukere.

### wp_fiskedagbok_name_mappings (Navn-mapping)
Lagrer mapping mellom fisher_name og WordPress brukere for automatisk klaiming.

```sql
-- Hovedtabell struktur
CREATE TABLE wp_fiskedagbok_catches (
    id int(11) NOT NULL AUTO_INCREMENT,
    user_id int(11) DEFAULT NULL,
    catch_id varchar(255) DEFAULT NULL,
    date date NOT NULL,
    time_of_day time DEFAULT NULL,
    week int(2) DEFAULT NULL,
    river_id int(11) DEFAULT NULL,
    river_name varchar(255) DEFAULT NULL,
    beat_id int(11) DEFAULT NULL,
    beat_name varchar(255) DEFAULT NULL,
    fishing_spot varchar(255) DEFAULT NULL,
    fish_type varchar(100) NOT NULL,
    equipment varchar(100) DEFAULT NULL,
    weight_kg decimal(5,2) DEFAULT NULL,
    length_cm decimal(5,1) DEFAULT NULL,
    released tinyint(1) DEFAULT 0,
    sex enum('male','female','unknown') DEFAULT 'unknown',
    boat varchar(255) DEFAULT NULL,
    fisher_name varchar(255) DEFAULT NULL,
    notes text DEFAULT NULL,
    claimed tinyint(1) DEFAULT 0,
    claimed_by_user_id int(11) DEFAULT NULL,
    claimed_at datetime DEFAULT NULL,
    weather_data text DEFAULT NULL,
    weather_fetched_at datetime DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    platform_reported_from varchar(50) DEFAULT 'web',
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY claimed_by_user_id (claimed_by_user_id),
    KEY date (date),
    KEY fish_type (fish_type),
    KEY fisher_name (fisher_name)
);
```

## Shortcodes

### [fiskedagbok_form]
Viser skjema for registrering av nye fangster. Kun synlig for innloggede brukere.

**Attributter:** Ingen

**Eksempel:**
```
[fiskedagbok_form]
```

### [fiskedagbok_list]
Viser liste over brukerens fangster med mulighet for redigering og sletting.

**Attributter:** Ingen

**Eksempel:**
```
[fiskedagbok_list]
```

### [fiskedagbok_search]
Viser søkefunksjon for å finne eksisterende fangster basert på navn og mulighet for å klaime dem.

**Attributter:** Ingen

**Eksempel:**
```
[fiskedagbok_search]
```

## AJAX Endpoints

Pluginet registrerer følgende AJAX endpoints:

- `submit_catch` - Lagre ny fangst
- `get_catch` - Hent enkelt fangst for redigering
- `update_catch` - Oppdater eksisterende fangst
- `delete_catch` - Slett fangst
- `search_catches_by_name` - Søk etter fangster basert på navn (i arkiv)
- `claim_catch` - Klaim en eksisterende fangst fra arkiv til brukerens dagbok
- `get_catch_details` - Hent detaljert informasjon om en fangst inkludert værdata
- `fetch_weather_data` - Hent værdata for en spesifikk dato
- `import_csv_to_archive` - Admin: Import CSV til arkiv-tabell
- `admin_search_archive` - Admin: Søk i arkiv
- `admin_claim_to_user` - Admin: Tildel fangst fra arkiv til spesifikk bruker

Alle endpoints krever innlogget bruker og gyldig nonce for sikkerhet.

## Værdata

Pluginet inkluderer et forenklet værsystem som simulerer værdata basert på årstid og dato.

### Funksjonalitet
- Automatisk generering av værdata når fangstdetaljer vises
- Manual henting via "Hent værdata" knapp
- Viser temperatur, nedbør, vindhastighet og vindretning
- Sesongbaserte temperaturer som reflekterer norske forhold
- Værdata lagres i databasen for rask visning

### Integrering med frost.met.no (valgfritt)
For ekte værdata kan du integrere med Meteorologisk instituttets API:

1. Registrer deg på [frost.met.no](https://frost.met.no/) 
2. Få din gratis Client ID
3. Erstatt `fetch_weather_for_date()` metoden i `fiskedagbok.php`
4. Implementer ekte API-kall med din Client ID

### Simulerte værdata felter
- **Temperatur**: Sesongbasert temperatur ± variasjon
- **Nedbør**: Tilfeldig nedbørsmengde (mer hver 7. dag)
- **Vindhastighet**: 2-15 m/s
- **Vindretning**: 0-359 grader

## Fangst-klaiming

Brukere kan søke etter og klaime eksisterende fangster som er registrert med deres navn.

### Hvordan det fungerer
1. Bruker søker på sitt navn med `[fiskedagbok_search]` shortcode
2. Systemet finner fangster hvor `fisher_name` matcher søket
3. Bruker kan se detaljer om fangsten inkludert værdata
4. Bruker kan klaime fangsten med "Legg til i min dagbok" knapp
5. Fangsten blir tilknyttet brukerens konto og vises i deres personlige liste

### Navn-mapping
- Systemet lagrer automatisk mapping mellom `fisher_name` og WordPress brukere
- Fremtidige imports kan bruke denne mappingen for automatisk tilknytning
- Administratorer kan administrere navn-mappings i admin-panelet

- Alle user input blir sanitert
- CSRF beskyttelse via WordPress nonces
- Brukere kan kun se/redigere sine egne fangster
- Administratorer kan se alle fangster
- SQL injection beskyttelse via WordPress $wpdb

## Tilpasning

### CSS Klasser
Frontend elementene bruker følgende CSS klasser for tilpasning:

- `.fiskedagbok-form-container` - Hovedcontainer for skjema
- `.fiskedagbok-list-container` - Hovedcontainer for fangstliste
- `.catch-item` - Enkeltstående fangst i liste
- `.catch-header` - Header med dato og fisketype
- `.catch-details` - Detaljer om fangsten

### JavaScript Events
Pluginet dispatcher følgende JavaScript events:

- `fiskedagbok_catch_saved` - Når en fangst er lagret
- `fiskedagbok_catch_updated` - Når en fangst er oppdatert
- `fiskedagbok_catch_deleted` - Når en fangst er slettet

## Krav

- WordPress 5.0 eller nyere
- PHP 7.4 eller nyere
- MySQL 5.6 eller nyere
- Aktive brukerkontoer for registrering av fangster

## Support og Utvikling

### Rapporter Bugs
Rapporter bugs eller forespør nye funksjoner via GitHub issues.

### Bidra
1. Fork repositoriet
2. Opprett en feature branch
3. Gjør dine endringer
4. Test grundig
5. Send en pull request

## Lisens

GPL v2 eller senere

## Versionshistorikk

### v1.0.0
- Initial release
- Grunnleggende fangstregistrering
- Admin oversikt
- CSV import funksjonalitet
- Shortcodes for frontend visning