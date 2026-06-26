# CLAUDE.md – SkipsWeb Referanse

Teknisk og operativt referansedokument for AI-agenter (Claude Code, Codex, ChatGPT) som jobber med SkipsWeb.

---

## 1. Prosjektoversikt

**SkipsWeb** er en PHP/MySQL-basert webapplikasjon.

**Stack:**
```
Backend:     PHP 8.1+ (strict_types=1)
Database:    MySQL 8.0 (MySQLi), tegnsett utf8mb4
Frontend:    HTML5, CSS (app.css), vanilla JS
Miljø:       XAMPP (dev), web hosting (prod)
```

**Prinsipp:** Funksjonalitet og korrekthet > visuell modernisering

**Scope:**
- Prosjektet er **kun** `skipsweb`
- **Ingen** kode/mønstre fra andre repoer (f.eks. nmmprimus)
- Autoritative kilder: Dokumenter i `skipsweb`, eksisterende kode
- GitHub repo: <https://github.com/Kringla/skipsweb>

---

## 2. Styrende filer

Les alltid **før** koding:

| Fil | Formål |
|-----|--------|
| `_basis/SkipsWeb_PStrD.md` | Prosjektstrukturer, repo-struktur og filoversikt |
| `_basis/SkipsWeb_PReqD.md` | Krav og forutsetninger |
| `_basis/SkipsWeb_Schema.sql` | SkipsDb database-schema |
| `_basis/SkipsWeb_Fields.md` | Feltlengder for forms |
| `includes/functions.php` | Feltlengder og hjelpefunksjoner |
| `includes/bootstrap.php` | Bootstrap-informasjon |
| `config/constants.php` | Konstanter (**IKKE ENDRE**) |
| `assets/css/app.css` | Prosjektets CSS-klasser |

- Bruk alltid **nyeste versjon** (`v*` = høyest nummer).
- Vedlagte filer har **alltid prioritet** over filer i repo eller GitHub.
- Filene i `config/` skal **ikke** endres.

---

## 3. Kjernearkitektur

### Design prinsipp

Design responsivt slik at siden tilpasser seg ulike skjermstørrelser:
- Mobiltelefoner (smart phones)
- Nettbrett
- Laptop
- Store skjermer

Bruk viewport-bredde, responsive breakpoints og fleksible layouts. 

### Database Access: MySQLi Singleton

```php
$db = db();          // Read-only (cachet singleton)
$rw = db_rw();       // Read-write (ny tilkobling per kall)

// Lesing
$stmt = $db->prepare("SELECT * FROM table WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Skriving med transaksjon
$result = with_rw(function (mysqli $rw) use ($data) {
    $stmt = $rw->prepare("INSERT INTO table (col) VALUES (?)");
    $stmt->bind_param('s', $data);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
});
```

**Krav:**
- Prepared statements med `?`-parametere og `bind_param()`
- `get_result()->fetch_assoc()` for resultat
- `db()` for lesing, `db_rw()` / `with_rw()` for skriving
- Ingen rå SQL
- Tabeller som starter med `tblz` er **parametertabeller**

### Include-rekkefølge (KRITISK)

Sider ligger i `admin/` eller `user/` — ett nivå under rot.

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';   // config, constants, functions, db()
require_once __DIR__ . '/../includes/auth.php';

require_login();  // eller require_admin()

// Prosessering

$page_title = 'Tittel';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>

<!-- Innhold -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```

### Sikkerhet

```php
// Output escaping
echo h($userInput);

// Dynamiske bildekilder
echo basename($filename);

// CSRF (manuell håndtering per side)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf_token'];

// I form:
<input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

// Ved POST-mottak:
if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, (string) $_POST['csrf_token'])) {
    die('Ugyldig forespørsel (CSRF).');
}

// Auth
require_login();   // Sjekk innlogget
require_admin();   // Sjekk admin-rolle
```

### Tegnsett

- Database: utf8mb4
- Kollasjon primært: UTF-8_unicode_ci
- Kollasjon sekundært: UTF-8_danish_ci

---

## 4. CSS-regler

- Nye regler i `assets/css/app.css` – ikke inline `<style>`
- Eksisterende klasser skal beholdes
- Ikke bruk `!important` unntatt når helt nødvendig
- Scope endringer (f.eks. `.card.centered-card`)
- Tabeller skal være sentrert i kortet sitt
- Knapper på samme linje bør ha samme størrelse og tekstformat

---

## 5. JavaScript

- Ikke endre `hero-rotator.js`

---

## 6. Vanlige oppgaver

### Ny side/modul

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

// Prosessering

$page_title = 'Tittel';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/menu.php';
?>

<!-- HTML -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```

### Database-funksjon

```php
function din_funksjon(string $param): array
{
    $db = db();
    $stmt = $db->prepare("SELECT * FROM table WHERE field = ?");
    $stmt->bind_param('s', $param);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    $stmt->close();
    return $rows;
}
```

### API-endepunkt

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Kun POST']);
    exit;
}

$result = din_funksjon();
echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
```

---

## 7. Sjekkliste for ny kode

- [ ] `declare(strict_types=1);` først
- [ ] Prepared statements med `bind_param()`
- [ ] Output escaped med `h()`
- [ ] `basename()` på dynamiske filnavn/bildekilder
- [ ] CSRF-token på POST-forms
- [ ] Include-rekkefølge: `bootstrap.php` → `auth.php` → prosessering → `header.php` → `menu.php` → innhold → `footer.php`
- [ ] `header.php` før output, `footer.php` på slutten
- [ ] Ingen duplisering av eksisterende funksjoner eller CSS-klasser
- [ ] `require_login()` eller `require_admin()`
- [ ] Bruk konstanter fra `config/constants.php`
- [ ] Norske, beskrivende navn
- [ ] Validert mot krav i CR og relevante prosjektdokumenter
- [ ] Fjernet "temp"-filer som kun var til bruk under kodingsarbeidet
- [ ] **Angi alltid hvilke filer som må overføres til produksjon etter endringer**

---

## 8. Testplan

- Lokalt (XAMPP): Last siden(e) direkte og verifiser layout. Hard refresh med `CTRL+F5`.
- Nettleser: Chrome
- Skjermbredder: 375 px (telefon), 768 px (nettbrett), 1100 px (laptop) og større

---

## 9. Deployment til produksjon

**Etter enhver kodeendring skal du alltid angi hvilke filer som må overføres til produksjon.**

### Format for deployment-liste:

```markdown
## Filer å overføre til produksjon:

1. `path/til/endret_fil.php`
2. `path/til/annen_fil.php`

**Nye filer:**
- `path/til/ny_fil.php`

**Filer å slette:**
- `path/til/gammel_fil.php`

**Test etter deployment:** Kort beskrivelse av hva som bør verifiseres.
```

### Filer som IKKE er i git og må finnes på serveren

Disse filene inneholder miljøspesifikke innstillinger og passord. De er ikke i git og må alltid ligge på produksjonsserveren:

| Fil | Innhold |
|-----|---------|
| `config/config.php` | DB-host, navn, bruker, passord |
| `config/constants.php` | `define('BASE_URL', '/');` og `define('SKIPSWEB_THEME_V2', true);` |

Ved nyinstallasjon: kopier `_basis/configLive.php` → `config/config.php` på serveren, og opprett `config/constants.php` manuelt.

### Retningslinjer:

1. Liste alle endrede filer – bruk relative paths fra prosjektrot
2. Marker nye filer – angi tydelig hvilke filer som er nye
3. Angi filer som skal slettes – hvis noen filer skal fjernes i produksjon
4. Kort forklaring – hvis nødvendig, forklar hva hver fil gjør
5. Legg deployment-listen på slutten av svaret/oppsummeringen

---

## 10. Feilsøking

| Feil | Årsak | Løsning |
|------|-------|---------|
| "Could not connect" | MySQL ikke startet | Start XAMPP MySQL |
| Hvit side / 500 | Syntaksfeil | Sjekk apache/logs/error.log |
| CSS lastes ikke | Feil BASE_URL | Sjekk constants.php |
| Sesjon tapt | Cookie-problem | Sjekk session-konfig |

---

## 11. Når du står fast

1. Sjekk styrende filer (se seksjon 2)
2. Sjekk kodekommentarer
3. **Stopp og spør** – aldri gjett

**Kontakt:** webmaster@skipsweb.no

---

**Versjon:** 2.1
**Sist oppdatert:** 2026-06-26
