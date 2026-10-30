# Claude-Code-Skills für dieses Projekt

Welche Claude-Code-Skills zu diesem Projekt passen, wann man sie einsetzt und wie.
Sortiert nach dem üblichen Ablauf: Issue → Feature-Branch → Umsetzung → Verifikation →
PR → `git merge --no-ff` in `small` → `CLAUDE.md` nachziehen.

Stand: 2026-10-30. Projekteigene Skills (`.claude/skills/`) gibt es noch keine — siehe
„Eigene Projekt-Skills" unten.

## 1. Planung (neue Features, „Big Update"-Issues)

| Skill | Wann | Wie |
|---|---|---|
| `superpowers:brainstorming` | Vor jedem neuen Feature / jeder Verhaltensänderung (z.B. die geparkten Visionen #12, #19, #1, #83-Rest) | `/superpowers:brainstorming` + Issue-Nummer. Klärt den Umfang, bevor Code entsteht — hilft, die „bewusst klein"-Linie zu halten. |
| `superpowers:writing-plans` | Nach dem Brainstorming bei Arbeit über mehrere Dateien (Controller + Model + View + Migration + Tests) | Macht aus dem Design einen Schritt-für-Schritt-Plan mit Dateien und Tests je Schritt. |
| `engineering:architecture` | Echte Architekturentscheidungen (Typ „Registry + Soft-Link statt voller Normalisierung", #61) | Schreibt ein ADR — gut für „Bewusst eingeführt / Bewusst entfernt", damit die Begründung erhalten bleibt. |

## 2. Umsetzung

| Skill | Wann | Wie |
|---|---|---|
| `superpowers:test-driven-development` | Jeder Bugfix, jeder neue pure Seam (`sollVersenden`, `undoIdsAus`, `emailAktion` …) | Erst den fehlschlagenden Test in `tests/unit/`, ausführen mit `docker exec kassensystem-vdst-web vendor/bin/phpunit tests/unit/`, dann implementieren. Passt zum DB-losen Seam-Muster. |
| `superpowers:executing-plans` | Einen Plan aus `writing-plans` in einer Session abarbeiten | Aufgabe für Aufgabe mit Checkpoints. |
| `superpowers:subagent-driven-development` / `dispatching-parallel-agents` | Nur für Pakete unabhängiger Tech-Debt-Issues (wie #89–#93) | ⚠️ Worktree-Subagenten können nicht testen (der Container mountet nur den Haupt-Checkout) — Verifikation beim Merge auf `small`. |
| `superpowers:using-git-worktrees` | Gleicher Vorbehalt; nur für parallele Branches | Meist reicht ein normaler Feature-Branch. |

## 3. Debugging

| Skill | Wann | Wie |
|---|---|---|
| `superpowers:systematic-debugging` | Jeder Bug, rote Test, merkwürdiges Verhalten (CSRF-403, dompdf-Umlaute, Redirect-Loops wie #72) | Reproduzieren → eingrenzen → Ursache → *dann* fixen. Verhindert „raten und flicken". |
| `engineering:debug` | Alternative für „lokal geht's, in Prod nicht" | Strukturiertere Vorlage. Entweder diese oder die obere, nicht beide. |

## 4. Qualität vor dem PR

| Skill | Wann | Wie |
|---|---|---|
| `superpowers:verification-before-completion` | Immer, bevor etwas als „fertig" gilt / committet wird | Erzwingt tatsächliches Ausführen von phpunit, `php -l` und Curl-Smoke-Test inkl. Ausgabe. |
| `code-review` (eingebaut) | Vor jedem PR | `/code-review medium` auf dem Branch; `/code-review high` bei Geld, Auth, Uploads. |
| `simplify` | Nach Abschluss eines Features | `/simplify` — nur Wiederverwendung/Duplikate/Abstraktionsebene. Daraus entstanden schon #89–#93. |
| `security-review` | Änderungen an Auth, Uploads, Redirects, Dateiauslieferung, Mail | `/security-review` — prüft u.a. Open-Redirect-Guard, `uploads/.htaccess`, CSRF, `esc()`. |
| `superpowers:requesting-code-review` / `receiving-code-review` | Zweitmeinung einholen bzw. Review-Feedback abarbeiten | Letzteres prüft Vorschläge, statt sie blind zu übernehmen. |

## 5. Abschluss & Deployment

| Skill | Wann | Wie |
|---|---|---|
| `superpowers:finishing-a-development-branch` | Tests grün, Branch fertig | Führt durch PR (`--base small`, `Closes #N`) und lokales `merge --no-ff`. |
| `engineering:deploy-checklist` | Vor Produktiv-Deploy, besonders mit Migrationen | Backup → migrate → `.htaccess` → `CI_ENVIRONMENT`. Ergänzt `docs/BACKUP.md` und die `migrate:rollback -b N`-Falle. |
| `engineering:documentation` | README, `docs/BACKUP.md`, Runbooks | Die `CLAUDE.md`-Pflege-Regel macht das zur Daueraufgabe. |

## 6. Pflege & gelegentlich

| Skill | Wann | Wie |
|---|---|---|
| `engineering:tech-debt` | Alle paar Monate oder bei leerem Backlog | Audit + Priorisierung → Issues mit Label `tech-debt`. |
| `engineering:testing-strategy` | Bei der Frage nach Testlücken (Controller und Models mit DB-Zugriff sind ungetestet) | Testplan — z.B. ob DB-Integrationstests bei dieser Größe lohnen. |
| `design:accessibility-review` | Nach UI-Änderungen (Darkmode-Kontrast, Icon-only-Buttons, `<details>`-Hilfe-Tipps) | Passt zu den bestehenden `aria-label`/`title`-Regeln. |
| `design:design-system` | Wenn `app.css`-Tokens oder Button-/Badge-Regeln auseinanderlaufen | Prüft auf rohe Farbwerte und inkonsistente Klassen gegen die Regeln aus #47/#14. |
| `design:ux-copy` | Deutsche Texte: Flash-Meldungen, Leerzustände, Mailtexte (`baueMail` etc.) | Wortwahl und Ton prüfen. |
| `anthropic-skills:xlsx` / `pdf` | Exporte prüfen (Abrechnungs-Excel, Inventur-PDF) oder eine neue Getränkewart-Excel vor Parser-Änderungen inspizieren (#107) | Liest die Dateistruktur ohne PHP. |
| `run` | Eine Änderung in der echten App sehen | Wird mit einem Projekt-Skill (s.u.) deutlich nützlicher. |
| `fewer-permission-prompts` | Einmalig | `settings.local.json` enthält sehr enge Einmal-Freigaben; ergänzt sinnvolle wie `docker exec kassensystem-vdst-web *`. |

## Passen NICHT zum Projekt

- `small-business:*` (invoice-chase, close-month, cash-flow …) — setzen QuickBooks/Xero/Stripe-Connectoren voraus; hier läuft ein selbstgebautes Vereins-Kassenbuch.
- `marketing:*`, `game-trend-skills:*`, Shopify-Tools.
- `engineering:system-design`, `engineering:incident-response` — Enterprise-Maßstab, widerspricht „bewusst klein".
- `code-review` auf Stufe `max`/`ultra` — zu teuer für ein Projekt mit ein paar Belegen pro Woche.

## Eigene Projekt-Skills (Vorschlag, noch nicht umgesetzt)

Mit `superpowers:writing-skills` bzw. `anthropic-skills:skill-creator` in `.claude/skills/` anlegen:

1. **`vdst-test`** — phpunit + `php -l` auf geänderte Dateien im Container (Testliste per Glob statt der veraltenden Liste in `CLAUDE.md`).
2. **`vdst-smoke`** — Curl-Login-Flow (CSRF-Token, Master-Passwort aus `.env`) + Statuscheck der Kernseiten; macht `run` nützlich.
3. **`vdst-ship`** — Feature-Branch → PR `--base small` → `merge --no-ff` → push → `CLAUDE.md` nachziehen.
4. **`vdst-migration`** — Migrationsregeln (selbstständig, nie an lebenden App-Code koppeln, vorher Backup, `-b N`-Falle).

## Typischer Ablauf

brainstorming → writing-plans → test-driven-development → verification-before-completion →
`/code-review` (+ `/security-review`, wo relevant) → finishing-a-development-branch.
`systematic-debugging`, sobald etwas bricht; `/simplify` und `engineering:tech-debt` ab und zu.
