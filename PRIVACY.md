# Privacy

Recovery Interface is **self-hosted software**: whoever runs an instance controls its database, and the data never leaves that server. This page describes what the software stores and why, so that anyone running it, and anyone using an instance, knows what to expect.

## What is stored

| Data | Why | Required? |
|---|---|---|
| Name, email | Account identification and sign-in | Yes |
| Password | Sign-in. Stored only as a **bcrypt hash**; the password itself is never stored or logged | Yes |
| Workouts: date, exercises, sets, reps, weight, RPE, optional label/notes | The core function: computing muscle readiness | To use the app |
| Profile: body weight, age, sex, height, activity level | Personalises the model (relative load, recovery speed) | **No.** Without a profile, the app uses an unpersonalised baseline |
| Failed sign-in attempts: IP address, **hashed** email, timestamp | Protection against password guessing | Automatic. Entries older than 24 hours are purged periodically |

Height is stored for display only and is not used in any calculation.

## What is *not* collected

- No analytics, tracking pixels or advertising.
- No third-party requests: fonts, scripts and styles are all served by the instance itself, so your IP address isn't shared with any CDN.
- Exactly **one cookie**: the session cookie (`HttpOnly`, `SameSite=Lax`), strictly necessary to keep you signed in. It is deleted when the browser closes.
- Unit preferences (kg/lb) are kept in your browser's local storage and never sent to the server.

## Sensitive data

Body weight, age, sex and training data can count as **health data** under the GDPR (Art. 9), depending on the context. If you run a public instance for other people, you are the data controller. You need a lawful basis, which for this category usually means explicit consent, and you should tell your users how long you keep their data.

Children: the profile accepts ages from 12. In Portugal, someone under 13 needs parental consent for this kind of service (GDPR Art. 8), and the threshold is higher in some other EU countries. A public instance should set its minimum age accordingly.

## Your data

- **Export.** While signed in, open `pages/api_export_data.php?format=json` (or `?format=csv`) to download your complete training history.
- **Backups.** `database/backup.php` keeps the 14 most recent dumps. Each one contains every account's data, so deleted data also disappears from backups only once older dumps rotate out.
- **Deletion.** Self-service account deletion is on the [roadmap](README.md#roadmap). Until then, the operator of the instance can delete an account. This removes the profile and every workout with it (cascading delete).

## Security measures

Passwords are hashed with bcrypt, and every state-changing request is CSRF-protected. Sign-in attempts are rate-limited, and a strict Content-Security-Policy is enforced. Internal errors are logged on the server and never shown to users. Details are in the [README](README.md#engineering-highlights).

## Not medical advice

Readiness scores come from a heuristic model ([docs/recovery-model.md](docs/recovery-model.md)). They are a training-planning aid, not a medical or physiological assessment.
