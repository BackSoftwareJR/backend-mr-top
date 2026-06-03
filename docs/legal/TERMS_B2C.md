# CONDIZIONI GENERALI DI UTILIZO DEL SERVIZIO WENANDO
## Per Utenti Consumatori (B2C) — Famiglie e Caregiver

---

| | |
|---|---|
| **Versione** | **1.0.0** |
| **Data di entrata in vigore** | **Giugno 2026** |
| **Servizio** | Wenando — Trust Engine per l'orientamento nell'assistenza agli anziani (Senior Care) |
| **Titolare / Fornitore** | Julian Rovera |
| **Denominazione commerciale** | Wenando |
| **Partita IVA** | IT13227980011 |
| **Email** | [hola@wenando.com](mailto:hola@wenando.com) |
| **Sito web** | [https://wenando.com](https://wenando.com) |
| **URL documento** | [https://wenando.com/terms](https://wenando.com/terms) |

---

## PREMESSE

**(A)** Julian Rovera, con denominazione commerciale **Wenando**, Partita IVA **IT13227980011**, con sede operativa e contatti al domicilio digitale **hola@wenando.com** e sito **https://wenando.com** (di seguito, il **«Fornitore»** o **«Wenando»**), mette a disposizione degli utenti consumatori una piattaforma digitale di orientamento informativo gratuito nel settore dell'assistenza agli anziani e delle cure di prossimità (Senior Care).

**(B)** Le presenti **Condizioni Generali di Utilizzo per Utenti Consumatori** (di seguito, le **«Condizioni»** o il **«Contratto»**) regolano l'accesso e l'utilizzo del sito web, delle applicazioni web e di ogni servizio digitale Wenando rivolto a **consumatori** ai sensi del D.Lgs. 26 maggio 2005, n. 206 (**«Codice del Consumo»**), nonché ai sensi del D.Lgs. 9 aprile 2003, n. 70, in materia di commercio elettronico e servizi della società dell'informazione.

**(C)** L'utilizzo del servizio implica la piena conoscenza e l'accettazione integrale delle presenti Condizioni, dell'[Informativa sulla Privacy](./PRIVACY_POLICY.md) (pubblicata anche su [https://wenando.com/privacy](https://wenando.com/privacy)) e della [Cookie Policy](./COOKIE_POLICY.md) (pubblicata anche su [https://wenando.com/cookies](https://wenando.com/cookies)), nella versione in vigore al momento dell'utilizzo.

**(D)** Le presenti Condizioni sono redatte in conformità ai principi di trasparenza, correttezza professionale e tutela del consumatore di cui agli artt. 33 e ss. del Codice del Consumo, al Regolamento (UE) 2016/679 (**«GDPR»**), al D.Lgs. 196/2003 (Codice Privacy) come modificato dal D.Lgs. 101/2018, e alla normativa applicabile in materia di contratti a distanza ove rilevante.

**(E)** Il servizio Wenando offerto ai consumatori è **gratuito**. Wenando **non** è un prestatore di servizi sanitari o socio-sanitari, **non** è un intermediario contrattuale tra l'utente e le strutture partner, e **non** conclude per conto dell'utente alcun contratto di ospitalità, assistenza, RSA, cure domiciliari o altro rapporto con i partner. Eventuali rapporti contrattuali si instaurano **esclusivamente** tra l'utente e la struttura o l'operatore scelto.

---

## PARTE I — DISPOSIZIONI GENERALI

### Art. 1 — Definizioni

Ai fini delle presenti Condizioni, i termini di seguito indicati avranno il significato qui di seguito specificato. Le definizioni si applicano al singolare e al plurale, al maschile e al femminile, salvo diversa indicazione del contesto.

**1.1** **«Utente»** o **«Consumatore»**: persona fisica che accede o utilizza la Piattaforma Wenando per scopi estranei all'attività imprenditoriale, commerciale, artigianale o professionale eventualmente svolta, ai sensi dell'art. 3, comma 1, lett. a), del Codice del Consumo. Include, ove applicabile, il familiare, il caregiver o il legale rappresentante che agisce per conto di una persona assistita.

**1.2** **«Persona assistita»**: il soggetto per il quale l'Utente ricerca informazioni o orientamento assistenziale, ove distinto dall'Utente medesimo.

**1.3** **«Piattaforma»** o **«Servizio»**: l'insieme del sito web **https://wenando.com**, delle relative interfacce web (SPA), delle API pubbliche di orientamento, del percorso guidato (wizard), dell'area utente (se attiva), delle pagine di risultati e di ogni funzionalità digitale resa disponibile da Wenando agli Utenti consumatori.

**1.4** **«Wizard»**: il percorso guidato multi-step mediante il quale l'Utente fornisce informazioni relative a esigenze assistenziali, localizzazione, budget, livello di autonomia, dati di contatto e altre informazioni utili al matching algoritmico.

**1.5** **«Lead»** o **«Richiesta di orientamento»**: l'insieme delle informazioni trasmesse dall'Utente al completamento del wizard e registrate sulla Piattaforma, identificata internamente mediante riferimento pubblico (es. `ML-####`).

**1.6** **«Partner»** o **«Struttura partner»**: persona giuridica o impresa registrata sulla piattaforma B2B Wenando, approvata mediante processo di vetting (`vetting_status = approved`), che offre servizi di assistenza agli anziani o servizi affini nel settore abilitato.

**1.7** **«Match»**: l'associazione algoritmica tra un Lead e uno o più Partner, con indicazione di un punteggio di compatibilità (**«match score»**).

**1.8** **«Marketplace B2B»**: l'area riservata ai Partner mediante la quale questi visualizzano Lead compatibili con dati **mascherati** fino all'operazione di unlock.

**1.9** **«Unlock»** o **«Sblocco»**: l'operazione mediante la quale un Partner, previo consumo di crediti dal proprio wallet B2B, accede ai dati di contatto completi del Lead (`unlocked_at`).

**1.10** **«Scenario A»**: modello giuridico GDPR applicabile al trasferimento del Lead al Partner, nel quale ciascun Partner approvato agisce quale **titolare autonomo del trattamento** per le proprie attività di contatto commerciale e assistenziale successivamente all'unlock, mentre Wenando resta titolare del trattamento relativo alla Piattaforma, al matching e al trattamento pre-trasferimento.

**1.11** **«Consenso versionato»**: il consenso o l'accettazione registrati nel registro immutabile `consent_logs`, con indicazione del tipo di consenso, della versione della policy (es. `1.0.0`), dell'hash SHA-256 del testo mostrato all'Utente, della data e ora, e degli identificativi tecnici di sessione.

**1.12** **«PII»** (Personally Identifiable Information): dati personali identificativi diretti, quali nome, cognome, numero di telefono, indirizzo email.

**1.13** **«Dati di categoria particolare»**: dati personali che rivelano l'origine razziale o etnica, le opinioni politiche, le convinzioni religiose o filosofiche, l'appartenenza sindacale, nonché dati genetici, dati biometrici, dati relativi alla salute o alla vita sessuale o all'orientamento sessuale, ai sensi dell'art. 9 GDPR. Nel contesto Wenando, includono, a titolo esemplificativo, informazioni sul livello di autonomia, sulle esigenze assistenziali e sui bisogni di cura.

**1.14** **«Documenti collegati»**: l'[Informativa sulla Privacy](./PRIVACY_POLICY.md), la [Cookie Policy](./COOKIE_POLICY.md) e le [Condizioni Generali di Piattaforma Partner B2B](./TERMS_B2B_PARTNERS.md), nella misura in cui disciplinano il modello di condivisione Lead con i Partner.

---

### Art. 2 — Identità del Fornitore e dati di contatto

**2.1** Il Fornitore del Servizio Wenando per gli Utenti consumatori è:

| Campo | Valore |
|-------|--------|
| **Titolare / Fornitore** | Julian Rovera |
| **Denominazione commerciale** | Wenando |
| **Partita IVA** | IT13227980011 |
| **Email generale e reclami** | [hola@wenando.com](mailto:hola@wenando.com) |
| **Sito web** | [https://wenando.com](https://wenando.com) |

**2.2** Per ogni comunicazione relativa alle presenti Condizioni, all'esercizio dei diritti privacy o alla presentazione di reclami, l'Utente può contattare Wenando all'indirizzo **hola@wenando.com**, indicando nell'oggetto la natura della richiesta (es. «Reclamo B2C», «Assistenza Wenando», «Diritti privacy»).

**2.3** Wenando, in quanto piccola e media impresa, **non ha nominato un Responsabile della Protezione dei Dati (DPO)** ai sensi dell'art. 37 GDPR. Per questioni relative al trattamento dei dati personali, l'Utente può rivolgersi direttamente al Fornitore all'indirizzo **hola@wenando.com**.

---

### Art. 3 — Campo di applicazione, destinatari e natura del rapporto

**3.1** Le presenti Condizioni si applicano esclusivamente all'utilizzo della Piattaforma Wenando da parte di **Utenti consumatori** (segmento B2C), ivi compresi familiari, caregiver e legittimi rappresentanti che agiscono per conto di una persona assistita.

**3.2** Le presenti Condizioni **non** si applicano ai Partner B2B (strutture e operatori commerciali), i quali sono soggetti alle [Condizioni Generali di Piattaforma Partner B2B](./TERMS_B2B_PARTNERS.md).

**3.3** Il rapporto giuridico instaurato tra Wenando e l'Utente consumatore ha ad oggetto esclusivamente l'**accesso gratuito** al Servizio di orientamento informativo e alle funzionalità digitali descritte nel presente Contratto. Tale rapporto **non** comprende la prestazione di servizi sanitari, socio-sanitari, di cura, di ospitalità, di assistenza domiciliare o di mediazione contrattuale verso strutture terze.

**3.4** L'Utente riconosce che Wenando opera quale **fornitore di servizi della società dell'informazione** ai sensi del D.Lgs. 70/2003, mettendo a disposizione strumenti digitali di raccolta informazioni, profilazione orientativa e presentazione di risultati di matching, senza assumere obbligazioni di risultato assistenziale, clinico o contrattuale nei confronti dell'Utente o della persona assistita.

**3.5** In caso di contrasto tra le presenti Condizioni e quanto eventualmente comunicato in materiali promozionali, descrizioni marketing o comunicazioni non vincolanti, **prevalgono le presenti Condizioni** nella versione pubblicata sul sito e accettata dall'Utente.

---

### Art. 4 — Oggetto del Servizio: guida orientativa gratuita

**4.1** Wenando mette a disposizione dell'Utente, **senza corrispettivo** da parte dell'Utente consumatore, un servizio digitale di **orientamento informativo** finalizzato ad aiutare l'Utente a:

- (a) comprendere e strutturare le proprie esigenze assistenziali o quelle della persona assistita;
- (b) individuare, mediante algoritmi di matching, strutture o operatori Partner potenzialmente compatibili con tali esigenze;
- (c) visualizzare schede informative sulle Strutture partner e punteggi di compatibilità orientativi;
- (d) eventualmente, previo consenso esplicito, consentire ai Partner selezionati di ricontattare l'Utente in merito alla Richiesta di orientamento.

**4.2** Il Servizio include, a titolo esemplificativo e non esaustivo:

- il Wizard di orientamento multi-step;
- la presentazione dei risultati di matching e delle schede Struttura;
- l'eventuale creazione e gestione di un account utente con autenticazione OTP;
- l'area personale per la consultazione dello stato della richiesta (ove attiva);
- comunicazioni transazionali di servizio (es. conferma richiesta, aggiornamenti rilevanti);
- strumenti di esercizio dei diritti privacy e gestione dei consensi (ove implementati).

**4.3** Il Servizio **non** prevede, nella versione 1.0.0, alcun corrispettivo monetario a carico dell'Utente consumatore per l'utilizzo del Wizard, la visualizzazione dei risultati o l'accesso alle funzionalità B2C descritte. Eventuali future funzionalità a pagamento direttamente nei confronti del consumatore saranno oggetto di condizioni specifiche, informative precontrattuali aggiornate e, ove applicabile, diritto di recesso.

**4.4** Wenando si riserva di modificare, ampliare, limitare o sospendere temporaneamente o definitivamente singole funzionalità del Servizio, previa comunicazione agli Utenti ove ragionevolmente possibile e salvo esigenze di sicurezza o obblighi di legge che impongano interventi immediati.

---

### Art. 5 — Natura escludente del Servizio: cosa Wenando non è

**5.1** L'Utente prende atto, riconosce e accetta che Wenando **non** è e non si configura quale:

**(a) Prestatore di servizi sanitari, socio-sanitari o di cura.** Wenando non eroga prestazioni sanitarie, infermieristiche, riabilitative, psicologiche, assistenziali o di cura, né dispone di personale sanitario abilitato per finalità cliniche. Le informazioni fornite attraverso la Piattaforma non costituiscono diagnosi, prescrizione, parere medico o indicazione terapeutica.

**(b) Ente pubblico, ente accreditato o organismo di certificazione.** Wenando non è un ente del Servizio Sanitario Nazionale, un'Autorità sanitaria locale, un organismo di accreditamento istituzionale o un certificatore ufficiale della qualità, della conformità normativa o dell'idoneità delle Strutture partner.

**(c) Intermediario contrattuale o agente.** Wenando **non** agisce quale intermediario contrattuale, mediatore, procuratore, agente o rappresentante dell'Utente o delle Strutture partner. Wenando **non** conclude, negozia, stipula, modifica o risolve contratti di ospitalità, degenza, assistenza residenziale (RSA), assistenza domiciliare (ADI), day care, servizi di cura o altri rapporti contrattuali tra l'Utente e i Partner.

**(d) Garante di esito assistenziale o clinico.** Wenando **non** garantisce l'esito clinico, assistenziale, organizzativo, economico o soddisfattivo di alcun trattamento, percorso di cura, soggiorno o prestazione resa da Strutture terze.

**5.2** Le informazioni, i punteggi di compatibilità (**«match score»**), le schede Struttura, i riepiloghi generati dal Wizard, le etichette di orientamento (es. suggerimenti di tipologia assistenziale) e ogni altro contenuto informativo reso disponibile dalla Piattaforma hanno **valore puramente orientativo, informativo e non vincolante**. Essi **non** sostituiscono il parere di medici, infermieri, assistenti sociali, educatori professionali o altri professionisti abilitati, né la valutazione multidimensionale che l'Utente è invitato a richiedere alle Strutture e agli operatori competenti.

**5.3** L'Utente è l'unico responsabile delle decisioni assunte sulla base delle informazioni orientative della Piattaforma e dell'eventuale instaurazione di rapporti con Strutture partner. Wenando raccomanda all'Utente di:

- verificare autonomamente visure camerali, autorizzazioni sanitarie, accreditamenti, contratti e condizioni economiche prima di impegnarsi con una Struttura;
- consultare il medico di medicina generale, lo specialista o l'assistente sociale ove necessario;
- effettuare visite di conoscenza, colloqui e valutazioni personalizzate presso le Strutture prescelte.

**5.4** Wenando non effettua sopralluoghi, ispezioni periodiche obbligatorie o audit clinici sulle Strutture partner, salvo quanto eventualmente previsto nel processo di vetting B2B (cfr. [Condizioni B2B](./TERMS_B2B_PARTNERS.md)), che non equivale a certificazione sanitaria o autorizzazione istituzionale.

---

### Art. 6 — Rapporto contrattuale esclusivo tra Utente e Struttura partner

**6.1** Eventuali contatti telefonici, email, messaggi, visite di conoscenza, sopralluoghi, preventivi, proposte economiche, contratti preliminari, contratti definitivi, moduli di ammissione, pagamenti, rimborsi, recessi, reclami assistenziali e ogni altro rapporto giuridico o de facto relativo ai servizi assistenziali si instaurano **esclusivamente e direttamente tra l'Utente (o la persona assistita e i suoi legittimi rappresentanti) e la Struttura o l'operatore Partner scelto**, alle condizioni concordate tra tali parti.

**6.2** Wenando **non** è parte di tali rapporti, **non** assume obbligazioni solidali o sussidiarie verso l'Utente per inadempimenti della Struttura, e **non** garantisce l'esecuzione delle prestazioni promesse dai Partner.

**6.3** L'Utente riconosce che:

- (a) i prezzi, le tariffe, le disponibilità di posti letto, camere o servizi indicati nelle schede Struttura o nelle comunicazioni dei Partner possono essere **indicativi** e soggetti a verifica, variazione e conferma diretta con la Struttura;
- (b) la scelta finale della Struttura e la sottoscrizione di eventuali contratti restano **libere e autonome** decisioni dell'Utente;
- (c) Wenando non è responsabile della veridicità, completezza o aggiornamento delle informazioni pubblicate dai Partner nei propri profili B2B, salvo obblighi di diligenza nella gestione della Piattaforma nei limiti di legge.

**6.4** Per controversie relative a prestazioni assistenziali, qualità del servizio reso dalla Struttura, danni alla persona assistita, inadempimenti contrattuali verso la Struttura o questioni di natura sanitaria, l'Utente dovrà rivolgersi **direttamente alla Struttura interessata** e, ove applicabile, alle autorità competenti (ASL, Garante sanitario regionale, Guardia di Finanza per aspetti commerciali, Autorità giudiziaria).

**6.5** Wenando può, a propria discrezione e senza obbligo, fornire all'Utente indicazioni di contatto della Struttura partner o segnalare al Partner l'esistenza di un reclamo, senza che ciò implichi assunzione di responsabilità o mediazione vincolante.

---

## PARTE II — ACCESSO, CONSENSI E OBBLIGHI DELL'UTENTE

### Art. 7 — Accesso al Servizio e accettazione delle Condizioni

**7.1** L'accesso alla Piattaforma e l'utilizzo del Servizio sono consentiti a Utenti **maggiorenni** (18 anni compiuti) o a soggetti che agiscono quale legale rappresentante, familiare autorizzato o caregiver munito di titolo legittimo per conto di una persona assistita.

**7.2** L'utilizzo del Wizard, la trasmissione di una Richiesta di orientamento (Lead), la creazione di un account (se prevista), l'accesso all'area utente e ogni altra interazione rilevante con la Piattaforma implicano l'**accettazione integrale, espressa e inequivocabile** delle presenti Condizioni nella versione in vigore al momento dell'utilizzo.

**7.3** L'accettazione delle Condizioni è registrata mediante Consenso versionato di tipo `terms_b2c`, con riferimento alla versione corrente (es. `1.0.0`) e hash del testo mostrato all'Utente, conformemente al sistema di `consent_logs` descritto nell'[Informativa sulla Privacy](./PRIVACY_POLICY.md).

**7.4** L'Utente dichiara di aver letto, compreso e accettato le presenti Condizioni, l'[Informativa sulla Privacy](./PRIVACY_POLICY.md) e la [Cookie Policy](./COOKIE_POLICY.md) **prima** di inviare la Richiesta di orientamento. L'invio del Lead senza valida accettazione registrata è tecnicamente impedito dalla Piattaforma (`POST /api/v1/leads` richiede consensi verificati).

**7.5** Se l'Utente non intende accettare integralmente le presenti Condizioni, dovrà astenersi dall'utilizzare il Servizio e dal trasmettere Richieste di orientamento.

---

### Art. 8 — Consensi prestati nel Wizard: testi, tipologie e registrazione

**8.1** Al passo finale del Wizard, prima dell'invio della Richiesta di orientamento, l'Utente deve prestare **consenso esplicito, libero, specifico, informato e inequivocabile** mediante checkbox **non preselezionate**, distinte per ciascuna finalità o documento, con testo chiaro e comprensibile.

**8.2** I consensi obbligatori per l'invio del Lead comprendono, al minimo, le seguenti tipologie:

| Tipo consenso (`consent_type`) | Oggetto | Obbligatorietà |
|--------------------------------|---------|----------------|
| `privacy_policy` | Informativa sulla Privacy | Obbligatorio |
| `terms_b2c` | Presenti Condizioni B2C | Obbligatorio |
| `lead_sharing` (o equivalente) | Condivisione dati con Partner | Obbligatorio per condivisione Partner |

**8.3** **Testo checkbox — Informativa Privacy e dati di categoria particolare (Art. 9 GDPR):**

> «Ho letto e accetto l'[Informativa sulla Privacy](https://wenando.com/privacy). Acconsento al trattamento dei miei dati personali, inclusi eventuali dati relativi al livello di autonomia e alle esigenze assistenziali (dati che possono rivelare informazioni sulla salute), per le finalità di orientamento, matching e gestione della mia richiesta, come descritto nell'Informativa.»

**8.4** **Testo checkbox — Condizioni Generali B2C:**

> «Ho letto e accetto le [Condizioni Generali di Utilizzo per Utenti Consumatori (B2C)](https://wenando.com/terms).»

**8.5** **Testo checkbox — Condivisione dati con Partner (Scenario A):**

> «Acconsento che Wenando condivida i miei dati con strutture partner selezionate per essere ricontattato/a in merito alla mia richiesta.»

**8.6** Il testo esatto delle checkbox visualizzate nell'interfaccia utente al momento dell'accettazione fa fede ai fini probatori, unitamente all'hash SHA-256 registrato in `consent_logs`. Wenando conserva prova del consenso per **5 (cinque) anni** dalla raccolta, con anonimizzazione di IP, user agent e session ID al termine del periodo, come indicato nell'[Informativa sulla Privacy](./PRIVACY_POLICY.md).

**8.7** **Consenso marketing (facoltativo, non attivo in v1.0.0):** qualora in futuro Wenando attivi comunicazioni promozionali, l'Utente dovrà prestare **consenso opt-in separato** (`consent_type: marketing`), non preselezionato. In assenza di tale consenso, Wenando invierà esclusivamente comunicazioni transazionali di servizio strettamente necessarie.

**8.8** **Consenso cookie analytics (facoltativo):** l'attivazione di strumenti di analytics (es. Plausible Analytics) richiede consenso separato tramite banner cookie (`consent_type: analytics_cookies`), come disciplinato nella [Cookie Policy](./COOKIE_POLICY.md). Il rifiuto non pregiudica l'utilizzo del Wizard e del Servizio principale.

**8.9** **Ordine tecnico di registrazione:** il frontend registra i consensi mediante `POST /api/v1/consents` **prima** di `POST /api/v1/leads`. L'Utente è informato che la revoca del consenso alla condivisione Partner (`lead_sharing`) impedisce il trasferimento futuro dei dati ai Partner, senza effetto retroattivo sui trattamenti già leciti.

**8.10** L'Utente può revocare in qualsiasi momento i consensi basati su consenso (Art. 7, comma 3, GDPR), scrivendo a **hola@wenando.com** con oggetto «Revoca consenso» o mediante strumenti dell'area utente ove disponibili. La revoca non pregiudica la liceità del trattamento basato sul consenso prestato prima della revoca.

---

### Art. 9 — Requisiti dell'Utente e dichiarazioni

**9.1** L'Utente dichiara e garantisce di:

- (a) essere maggiorenne o agire con legittimo titolo per conto di una persona assistita;
- (b) utilizzare il Servizio in **buona fede** e per finalità legittime di ricerca di orientamento assistenziale;
- (c) non utilizzare la Piattaforma per scopi fraudolenti, illeciti o contrari alle presenti Condizioni;
- (d) non impersonare terzi né fornire dati di contatto di terzi senza autorizzazione;
- (e) disporre della capacità giuridica di prestare i consensi richiesti, o agire quale rappresentante legittimo ove applicabile.

**9.2** L'Utente che agisce per conto di una persona assistita dichiara di avere titolo per rappresentarla o per trattare dati relativi alla medesima, e di aver informato la persona assistita ove richiesto dalla legge e dalle circostanze.

**9.3** Wenando si riserva il diritto di rifiutare, sospendere o limitare l'accesso al Servizio in caso di violazione delle presenti Condizioni, uso abusivo, tentativi di aggiramento delle misure di sicurezza, o segnalazioni fondate di condotta illecita.

---

### Art. 10 — Uso lecito del Servizio e condotte vietate

**10.1** L'Utente si impegna a utilizzare la Piattaforma in conformità alla legge applicabile, alle presenti Condizioni e ai principi di correttezza e buona fede contrattuale (art. 1175 c.c.).

**10.2** È **espressamente vietato** all'Utente:

**(a) Dati falsi o non autorizzati.** Fornire dati falsi, inventati, fuorvianti, incompleti in modo doloso, o dati personali di terzi senza titolo legittimo o consenso.

**(b) Abusi tecnici.** Utilizzare bot, scraper, crawler, script automatizzati o tecniche di reverse engineering per estrarre dati, eludere rate limiting, aggirare misure di sicurezza, accedere ad aree non autorizzate o sovraccaricare l'infrastruttura.

**(c) Aggiramento del marketplace.** Tentare di identificare Partner, Lead o PII attraverso canali diversi dal Servizio, aggirare il meccanismo di mascheramento pre-unlock, o contattare Partner con informazioni ottenute illicitamente.

**(d) Attività concorrenziale sleale.** Utilizzare il Servizio per finalità di intelligence commerciale, acquisizione sistematica di Lead altrui, concorrenza sleale verso Wenando o i Partner, o replica non autorizzata dell'offerta Wenando.

**(e) Violazione di proprietà intellettuale.** Copiare, riprodurre, distribuire, modificare, decodificare o sfruttare commercialmente contenuti, software, algoritmi, database, marchi o layout della Piattaforma senza autorizzazione scritta del Fornitore.

**(f) Contenuti illeciti o offensivi.** Trasmettere contenuti diffamatori, discriminatori, minacciosi, osceni o comunque illeciti attraverso il Wizard o i canali di comunicazione con Wenando.

**(g) Spam e molestie.** Inviare comunicazioni massicce, spam o molestie a Wenando, ai Partner o ad altri utenti.

**10.3** In caso di violazione del presente Articolo, Wenando può, a sua discrezione e senza pregiudizio di altri rimedi:

- sospendere o terminare l'accesso al Servizio;
- rifiutare l'elaborazione o la trasmissione di Lead;
- anonimizzare o cancellare dati associati all'account;
- segnalare le condotte alle autorità competenti;
- agire per il risarcimento del danno subito, nei limiti di legge.

---

### Art. 11 — Accuratezza, veridicità e aggiornamento dei dati

**11.1** L'Utente è **unico responsabile** della veridicità, accuratezza, completezza e aggiornamento dei dati e delle informazioni fornite nel Wizard, nel profilo utente e in ogni comunicazione con Wenando o i Partner.

**11.2** Dati e informazioni rilevanti includono, a titolo esemplificativo: nome e cognome o riferimento del contatto; numero di telefono; indirizzo email; città o area geografica; fascia di budget; livello di autonomia; esigenze assistenziali; sintesi del bisogno (`need_summary`); ogni altro campo del `payload` del Lead.

**11.3** Informazioni inesatte, obsolete o incomplete possono:

- (a) compromettere la qualità e l'affidabilità del matching algoritmico;
- (b) indurre in errore i Partner nel ricontatto;
- (c) generare esiti insoddisfacenti per l'Utente;
- (d) costituire violazione delle presenti Condizioni.

**11.4** Wenando **non** effettua verifica preventiva sistematica della veridicità di ogni dato dichiarato dall'Utente, salvo controlli automatizzati di formato, controlli anti-abuso e interventi su segnalazione. Wenando non garantisce che i Partner verifichino i dati ricevuti prima del ricontatto.

**11.5** L'Utente si impegna a comunicare tempestivamente a Wenando (hola@wenando.com) e, ove già avvenuto l'unlock, al Partner interessato, ogni rettifica sostanziale dei dati forniti, esercitando ove possibile il diritto di rettifica ex art. 16 GDPR.

---

## PARTE III — FUNZIONAMENTO DEL SERVIZIO E LIMITAZIONI

### Art. 12 — Wizard, matching e risultati di orientamento

**12.1** Il Wizard raccoglie informazioni fornite volontariamente dall'Utente, utili al calcolo del punteggio di compatibilità e alla presentazione di Strutture partner potenzialmente idonee.

**12.2** I risultati visualizzati dall'Utente:

- (a) **dipendono** dai dati forniti dall'Utente, dal catalogo Partner approvati al momento della richiesta, dai parametri algoritmici e dalle regole di business della Piattaforma;
- (b) **non** costituiscono una lista esaustiva, completa o ufficiale di tutte le strutture assistenziali disponibili sul territorio nazionale o locale;
- (c) **non** implicano raccomandazione medica, priorità clinica, urgenza sanitaria o indicazione terapeutica;
- (d) **non** garantiscono che le Strutture indicate abbiano disponibilità effettiva di posti, camere o servizi al momento del contatto;
- (e) possono variare nel tempo per aggiornamenti del catalogo Partner, modifiche algoritmiche o revoca dell'approvazione di singoli Partner.

**12.3** Il **match score** è un indicatore numerico o categorico di compatibilità **orientativa**, calcolato mediante processi automatizzati di profilazione descritti nell'[Informativa sulla Privacy](./PRIVACY_POLICY.md) (§ 12). Non produce effetti giuridici vincolanti nei confronti dell'Utente.

**12.4** L'Utente ha diritto di richiedere informazioni significative sulla logica del matching, esprimere il proprio punto di vista e contestare i risultati, scrivendo a **hola@wenando.com** con oggetto «Profilazione — richiesta informazioni», ai sensi degli artt. 13, 14 e 22 GDPR.

**12.5** Wenando può limitare il numero di Partner presentati, applicare criteri di ranking, escludere Partner non approvati o sospesi, e modificare i parametri di matching con preavviso ragionevole ove le modifiche incidano sostanzialmente sull'esperienza utente.

---

### Art. 13 — Disponibilità, prezzi indicativi e qualità delle Strutture partner

**13.1** Wenando **non garantisce**:

- (a) la **disponibilità** di posti letto, camere, unità di degenza, slot di assistenza domiciliare o altre capacità operative presso le Strutture partner;
- (b) la **correttezza, completezza o attualità** di prezzi, tariffe, promozioni, sconti o offerte commerciali eventualmente indicati nelle schede Struttura o nelle comunicazioni dei Partner (tali informazioni possono essere **meramente indicative**);
- (c) la **qualità**, l'idoneità, l'accreditamento, l'autorizzazione sanitaria, la conformità normativa, la sicurezza o l'affidabilità delle Strutture partner, salvo quanto eventualmente dichiarato dal Partner nel proprio profilo B2B e soggetto a verifica indipendente da parte dell'Utente;
- (d) l'**assenza di errori** nelle descrizioni, immagini, recensioni o metadati pubblicati dai Partner.

**13.2** Il processo di vetting B2B (documenti, trust test, approvazione amministrativa) mira a ridurre i rischi di partner inaffidabili, ma **non** equivale a certificazione sanitaria, garanzia di qualità assistenziale o assicurazione sull'esito del rapporto Utente–Struttura.

**13.3** L'Utente è **fortemente invitato** a:

- verificare autonomamente visure camerali, autorizzazioni ASL, accreditamenti, iscrizioni ad albi o registri ove applicabili;
- richiedere e leggere attentamente contratti, regolamenti interni, condizioni economiche e policy di recesso **prima** di sottoscrivere impegni con la Struttura;
- effettuare visite di conoscenza e colloqui con il personale qualificato;
- consultare autorità locali, associazioni di categoria e organismi di tutela del consumatore per informazioni aggiuntive.

---

### Art. 14 — Servizio erogato «così com'è» (as is)

**14.1** Il Servizio digitale Wenando è fornito all'Utente consumatore **«così com'è»** e **«come disponibile»** (*as is / as available*), nei limiti massimi consentiti dalla legge italiana e **senza pregiudizio** dei diritti inderogabili del consumatore di cui agli artt. 33 e ss. del Codice del Consumo.

**14.2** Ciò significa, in particolare, che Wenando:

- (a) non garantisce l'**assenza assoluta** di interruzioni, errori, bug, latenze, incompatibilità con dispositivi o browser specifici, perdita di dati dovuta a cause non imputabili a dolo o colpa grave di Wenando;
- (b) non garantisce la **continuità ininterrotta** del Servizio, potendo sospendere temporaneamente l'accesso per manutenzione, aggiornamenti, incidenti di sicurezza o cause di forza maggiore;
- (c) non garantisce che i risultati del matching soddisfino le aspettative soggettive dell'Utente o producano esito commerciale positivo con i Partner.

**14.3** Wenando si impegna, comunque, a mantenere un **ragionevole livello di diligenza professionale** nella progettazione, gestione e sicurezza della Piattaforma, conformemente alle best practice del settore e alla documentazione tecnica interna (Privacy by Design, misure di sicurezza ex art. 32 GDPR).

**14.4** L'Utente riconosce che l'utilizzo di servizi internet comporta rischi intrinseci (interruzioni di connettività, malfunzionamenti di dispositivi, attacchi informatici di terzi) non sempre eliminabili, e accetta di utilizzare il Servizio con tale consapevolezza, nei limiti di legge.

---

### Art. 15 — Limitazione di responsabilità (proporzionata)

**15.1** Fermo restando quanto previsto dall'**Art. 16** (diritti inderogabili del consumatore), e nei **limiti massimi consentiti** dalla legge applicabile, la responsabilità di Wenando verso l'Utente consumatore per fatti imputabili al Servizio gratuito di orientamento è disciplinata come segue.

**15.2** Wenando **non** è responsabile, in via generale e salvo i casi di cui al comma 15.5, per:

**(a)** danni, perdite, spese, controversie o pretese derivanti dal **rapporto diretto Utente–Struttura partner**, inclusi inadempimenti contrattuali, difetti delle prestazioni assistenziali, lesioni alla persona, danni patrimoniali o morali causati dalla Struttura o dal personale della Struttura;

**(b)** **decisioni** assunte dall'Utente o dalla persona assistita sulla base delle sole informazioni orientative, dei match score o delle schede Struttura presentate dalla Piattaforma;

**(c)** **condotte, omissioni, ritardi, errori o inadempimenti** dei Partner B2B successivamente al trasferimento dei dati (Scenario A), inclusi ricontatti non tempestivi, trattamenti illeciti dei dati da parte del Partner, uso dei dati per finalità diverse da quelle concordate, mancata cancellazione su richiesta dell'Utente — salvo obblighi specifici di Wenando quale titolare del trattamento pre-trasferimento o di piattaforma nei limiti di legge;

**(d)** **indisponibilità** di posti, variazioni di prezzo, chiusura o sospensione di attività delle Strutture partner;

**(e)** **danni indiretti**, lucro cessante, mancato guadagno, perdita di opportunità, danno reputazionale o danno emergente non direttamente causato da dolo o colpa grave di Wenando;

**(f)** **interruzioni** del Servizio dovute a manutenzione programmata, forza maggiore, guasti di terze parti (hosting, provider email, connettività), attacchi informatici non imputabili a negligenza grave di Wenando;

**(g)** **contenuti** generati o pubblicati dai Partner nei profili B2B, salvo obbligo di rimozione tempestiva su segnalazione fondata nei limiti di legge.

**15.3** Ove la responsabilità di Wenando fosse accertata con prova certa per fatti **direttamente imputabili** al Servizio gratuito di orientamento (es. malfunzionamento documentato della Piattaforma causante perdita della Richiesta prima del trasferimento al Partner, violazione accertata di obblighi di sicurezza ex art. 32 GDPR imputabile a Wenando), l'eventuale risarcimento — ove ammesso — sarà limitato al **danno diretto e documentato** subito dall'Utente, **esclusi** lucro cessante, danni indiretti e danni punitivi, nei limiti di legge e tenuto conto della natura **gratuita** del Servizio B2C.

**15.4** In nessun caso la responsabilità aggregata di Wenando verso un singolo Utente per tutti i fatti verificatisi in un periodo di **12 (dodici) mesi** potrà eccedere, ove ammesso dalla legge, l'importo simbolicamente corrispondente al valore del Servizio gratuito erogato, salvo dolo o colpa grave e salvo diversa previsione inderogabile di legge.

**15.5** **Eccezioni inderogabili.** Nulla nelle presenti Condizioni limita o esclude la responsabilità di Wenando per:

- (a) **dolo** o **colpa grave**;
- (b) morte o **danni alla persona** causati da negligenza di Wenando, nei limiti in cui la limitazione sia vietata dalla legge;
- (c) violazione di **diritti inderogabili del consumatore** ex art. 33 Codice del Consumo;
- (d) **danni causati da difetto del prodotto o del servizio digitale** ove applicabile normativa consumer su conformità (D.Lgs. 206/2005 e norme UE su contenuti e servizi digitali), nei limiti e alle condizioni di legge;
- (e) **trattamento illecito** dei dati personali imputabile a Wenando quale titolare, nei limiti degli artt. 82 GDPR e norme nazionali.

**15.6** L'Utente **manleva** Wenando da pretese di terzi (inclusi Partner) derivanti da dati falsi, illeciti o non autorizzati forniti dall'Utente, o da violazione delle presenti Condizioni da parte dell'Utente, nei limiti di legge.

---

### Art. 16 — Diritti inderogabili del consumatore

**16.1** Ai sensi dell'art. 33 del Codice del Consumo, le clausole che limitano o escludono diritti riconosciuti al consumatore dalla legge o che pongono a carico del consumatore oneri e rischi eccessivi rispetto alla natura del contratto sono **nulle** se non conformi ai requisiti di legge.

**16.2** Le limitazioni di responsabilità di cui all'Art. 15 devono essere interpretate **restrictivamente** e **non** possono essere invocate per eludere obblighi inderogabili, inclusi quelli relativi a:

- informazioni precontrattuali e trasparenza;
- conformità del servizio digitale ove applicabile;
- tutela della salute e sicurezza nei limiti di competenza del fornitore;
- protezione dei dati personali;
- rimedi previsti dal Codice del Consumo e dal GDPR.

**16.3** In caso di conflitto tra una clausola delle presenti Condizioni e una disposizione inderogabile di legge a tutela del consumatore, **prevalgono** le disposizioni di legge.

**16.4** L'Utente conserva il diritto di agire giudizialmente o extragiudizialmente per far valere i propri diritti, nei termini di legge e come indicato agli artt. 24 e 28 delle presenti Condizioni.

---

## PARTE IV — PROPRIETÀ INTELLETTUALE, PRIVACY E CONDIVISIONE DATI

### Art. 17 — Proprietà intellettuale e diritti sui contenuti

**17.1** Salvo diversa indicazione, tutti i diritti di proprietà intellettuale e industriale relativi alla Piattaforma Wenando appartengono al Fornitore Julian Rovera / Wenando o ai rispettivi licenzianti, inclusi a titolo esemplificativo:

- testi, grafica, layout, interfacce utente;
- logo, marchi, denominazioni «Wenando» e segni distintivi;
- software, codice sorgente e oggetto, API, architettura;
- database, strutture dati, algoritmi di matching, modelli di scoring;
- documentazione tecnica e materiali formativi resi disponibili agli Utenti.

**17.2** Wenando concede all'Utente una **licenza limitata, non esclusiva, non trasferibile, revocabile e gratuita** per accedere e utilizzare la Piattaforma esclusivamente per finalità personali e non commerciali di orientamento assistenziale, nel rispetto delle presenti Condizioni.

**17.3** È **vietato** all'Utente, salvo autorizzazione scritta del Fornitore:

- (a) riprodurre, copiare, distribuire, comunicare al pubblico, modificare, adattare o creare opere derivate dei contenuti o del software Wenando;
- (b) effettuare estrazione sistematica o reiterata di dati dal database (direct or indirect scraping);
- (c) decompilare, disassemblare o tentare di ricostruire il codice sorgente;
- (d) utilizzare marchi, logo o denominazioni Wenando in modo da generare confusione o suggerire affiliation non autorizzata;
- (e) rimuovere avvisi di copyright, marchi o restrizioni proprietarie.

**17.4** I contenuti forniti dall'Utente nel Wizard (testi liberi, descrizioni) restano di titolarità dell'Utente. L'Utente concede a Wenando una licenza non esclusiva, gratuita, limitata alla durata del trattamento, per utilizzare tali contenuti al solo fine di erogare il Servizio (matching, condivisione con Partner previo consenso, conservazione secondo retention).

**17.5** Wenando può utilizzare dati **aggregati e anonimizzati** (non identificativi) per statistiche, miglioramento del Servizio e report interni, conformemente all'[Informativa sulla Privacy](./PRIVACY_POLICY.md).

---

### Art. 18 — Privacy, trattamento dati e riferimenti normativi

**18.1** Il trattamento dei dati personali dell'Utente è disciplinato in dettaglio dall'[Informativa sulla Privacy](./PRIVACY_POLICY.md) (versione **1.0.0**, Giugno 2026), pubblicata su [https://wenando.com/privacy](https://wenando.com/privacy), che costituisce parte integrante del quadro contrattuale e informativo.

**18.2** Wenando tratta i dati personali in qualità di **Titolare del trattamento** ai sensi del GDPR per finalità di Piattaforma, matching, sicurezza, gestione consensi, comunicazioni transazionali e adempimenti di legge.

**18.3** L'Utente è invitato a consultare l'Informativa Privacy per informazioni su:

- categorie di dati trattati (identificativi, contatto, localizzazione, budget, autonomia, match score, consensi);
- basi giuridiche (Art. 6 e Art. 9 GDPR);
- destinatari e responsabili del trattamento (Art. 28);
- periodi di conservazione;
- diritti dell'interessato (Art. 15–22 GDPR);
- processi automatizzati e profilazione;
- trasferimenti extra-UE;
- reclamo al Garante Privacy.

**18.4** Per l'esercizio dei diritti privacy, l'Utente può scrivere a **hola@wenando.com** con oggetto «Diritti privacy — [accesso / rettifica / cancellazione / ecc.]». Wenando risponde entro **30 (trenta) giorni**, prorogabili di **60 (sessanta) giorni** in casi complessi, con comunicazione motivata entro i primi 30 giorni.

**18.5** Il trattamento dei cookie e tecnologie simili è disciplinato dalla [Cookie Policy](./COOKIE_POLICY.md).

---

### Art. 19 — Condivisione dati con Partner: Scenario A (titolari autonomi)

**19.1** Con il consenso esplicito prestato mediante la checkbox di cui all'Art. 8.5, l'Utente autorizza Wenando a **condividere** dati personali con **Strutture partner selezionate** (Partner con `vetting_status = approved` e match compatibile) al fine di consentire il ricontatto in merito alla Richiesta di orientamento.

**19.2** Il modello giuridico applicabile è lo **Scenario A** GDPR: ciascun Partner che riceve i dati dopo l'unlock agisce quale **titolare autonomo del trattamento** per le proprie attività di contatto commerciale, valutazione della richiesta, proposta di servizi assistenziali e gestione CRM, nel rispetto della propria informativa privacy e degli obblighi di legge.

**19.3** Wenando **non** determina le finalità del trattamento post-unlock da parte del Partner, salvo obblighi contrattuali B2B di minimizzazione, sicurezza e cessazione del trattamento su richiesta dell'Utente o di Wenando (cfr. [Condizioni B2B](./TERMS_B2B_PARTNERS.md)).

**19.4** Il trasferimento dei dati identificativi completi al Partner avviene **solo** dopo che:

- (a) l'Utente ha prestato consenso alla condivisione (`lead_sharing`);
- (b) il Lead è stato associato al Partner mediante matching;
- (c) il Partner ha effettuato l'**unlock** consumando crediti dal proprio wallet B2B (costo standard indicato in piattaforma: **15 crediti** per lead, salvo diversa comunicazione);
- (d) è registrato il timestamp `unlocked_at` nel sistema.

**19.5** **Prima dell'unlock**, i Partner **non** hanno accesso ai dati identificativi diretti dell'Utente (nome, telefono, email), conformemente al principio di minimizzazione (Art. 25 GDPR).

**19.6** L'Utente può revocare il consenso alla condivisione futura scrivendo a **hola@wenando.com**. La revoca non ha effetto retroattivo sui trattamenti già leciti. Wenando adotterà misure ragionevoli per informare i Partner che abbiano già ricevuto i dati della revoca e della necessità di cessare trattamenti non più coperti da base giuridica, nei limiti tecnicamente e giuridicamente possibili.

**19.7** Per controversie relative al trattamento dei dati **dopo** l'unlock, l'Utente può rivolgersi al Partner titolare autonomo e, ove applicabile, al Garante Privacy, oltre che a Wenando per gli aspetti di competenza di Wenando quale titolare pre-trasferimento.

---

### Art. 20 — Campi dati visibili ai Partner: fase pre-unlock e post-unlock

**20.1** Wenando applica un modello di **mascheramento progressivo** delle informazioni identificative nel marketplace B2B, in conformità al principio di minimizzazione (Art. 5, comma 1, lett. c), GDPR) e al piano di conformità legale interno Wenando (architettura privacy, non pubblicato sul sito).

**20.2** **Fase marketplace (pre-unlock)** — campi visibili al Partner:

| Campo | Descrizione | Visibilità pre-unlock |
|-------|-------------|----------------------|
| `location_label` | Città o area geografica (es. «Milano», «Roma Nord») | **Sì** |
| `budget_min` / `budget_max` | Fascia di budget indicata | **Sì** |
| `need_summary` | Sintesi testuale del bisogno assistenziale | **Sì** |
| `match_score` | Punteggio di compatibilità orientativo | **Sì** |
| Riferimento pubblico | Identificativo anonimo del Lead (es. `ML-####`) | **Sì** |
| `contact_name` | Nome e cognome del contatto | **No** |
| `contact_phone` | Numero di telefono | **No** |
| `contact_email` | Indirizzo email | **No** |
| `user_id` interno | Identificativo interno utente Wenando | **No** |

**20.3** **Fase post-unlock** — campi aggiuntivi resi disponibili al Partner:

| Campo | Descrizione | Visibilità post-unlock |
|-------|-------------|------------------------|
| `contact_name` | Nome e cognome del contatto | **Sì** |
| `contact_phone` | Numero di telefono | **Sì** |
| `contact_email` | Indirizzo email | **Sì** |
| `location_label` | Città o area geografica | **Sì** |
| `need_summary` | Sintesi del bisogno | **Sì** |
| `payload` (subset) | Sottoinsieme rilevante del payload wizard (es. `autonomy`, preferenze assistenziali) | **Sì** (limitato) |

**20.4** Wenando **non** espone al Partner l'identificativo interno `user_id` dell'Utente. Le comunicazioni e i riferimenti utilizzano identificativi pubblici del Lead.

**20.5** I Partner sono contrattualmente obbligati (Condizioni B2B) a:

- trattare i dati ricevuti **solo** per rispondere alla Richiesta Senior Care;
- **non** cedere i dati a terzi senza autonoma base giuridica;
- implementare misure di sicurezza adeguate (Art. 32 GDPR);
- assistere Wenando nelle richieste di esercizio diritti (DSAR) entro 30 giorni;
- notificare data breach a Wenando entro 24 ore.

**20.6** L'Utente è informato che, una volta sbloccato il Lead, il Partner può contattarlo telefonicamente, via email o altri canali utilizzando i dati ricevuti, secondo le proprie policy e obblighi di legge.

---

### Art. 21 — Periodi di conservazione dei dati (riferimento)

**21.1** I periodi di conservazione dei dati personali dell'Utente sono dettagliati nell'[Informativa sulla Privacy](./PRIVACY_POLICY.md) (§ 10). A titolo di riferimento sintetico per l'Utente consumatore:

| Dataset | Periodo | Azione al termine |
|---------|---------|-------------------|
| **Lead B2C inattivi** | **730 giorni** (24 mesi) dall'ultima attività | Anonimizzazione dati identificativi |
| **Lead B2C con account** | Fino a cancellazione o 730 gg inattività post-chiusura | Anonimizzazione e scollegamento account |
| **`lead_matches`** | Allineato al Lead collegato | Azzeramento riferimenti PII |
| **Registro consensi (`consent_logs`)** | **5 anni** dalla raccolta | Anonimizzazione IP/UA/session; conservazione hash |
| **Transazioni B2B** (non direttamente dell'Utente B2C) | **10 anni** | Obbligo fiscale |
| **Codici OTP** | **10 minuti** + purge | Cancellazione |
| **Log applicativi** | **30 giorni** | Rotazione |

**21.2** Trascorsi i termini, Wenando anonimizza, cancella o conserva i dati solo nella forma e per il tempo imposto da obblighi di legge, mediante job automatizzati (es. `leads:anonymize-stale`).

**21.3** L'Utente può richiedere la cancellazione anticipata nei limiti degli artt. 17 GDPR e § 13 dell'[Informativa Privacy](./PRIVACY_POLICY.md), tenuto conto delle eccezioni legali (prova del consenso, obblighi fiscali, difesa in giudizio).

---

## PARTE V — ACCOUNT, COMUNICAZIONI, RECLAMI E RIMEDI

### Art. 22 — Account utente e autenticazione

**22.1** Wenando può offrire all'Utente la possibilità di creare un **account personale** con autenticazione mediante **OTP** (One-Time Password) inviato via email o altri canali abilitati.

**22.2** L'Utente è responsabile della **riservatezza** delle credenziali, dei codici OTP e della sessione di accesso, nonché di ogni attività svolta tramite il proprio account.

**22.3** L'Utente deve informare tempestivamente Wenando (hola@wenando.com) in caso di sospetto accesso non autorizzato, compromissione dell'email o uso illecito dell'account.

**22.4** Wenando può sospendere o chiudere account inattivi, fraudolenti o in violazione delle presenti Condizioni, previa comunicazione ove ragionevolmente possibile.

**22.5** La chiusura dell'account non elimina automaticamente ogni obbligo di conservazione legale (consensi, log) né i trattamenti già effettuati dai Partner post-unlock, salvo richiesta di cancellazione e azioni di Wenando nei limiti di legge.

---

### Art. 23 — Comunicazioni tra Wenando e l'Utente

**23.1** Wenando può inviare all'Utente **comunicazioni di servizio** strettamente necessarie all'erogazione del Servizio, quali:

- conferma di ricezione della Richiesta di orientamento;
- codici OTP per autenticazione;
- notifiche su aggiornamenti rilevanti dello stato della richiesta;
- avvisi di modifiche sostanziali alle Condizioni o all'Informativa Privacy;
- risposte a reclami o richieste di assistenza.

**23.2** Tali comunicazioni sono inviate ai recapiti forniti dall'Utente (email, eventualmente SMS o altri canali indicati) e si considerano validamente effettuate se inviate all'ultimo recapito noto.

**23.3** **Comunicazioni promozionali e marketing:** nella versione **1.0.0**, Wenando **non** invia comunicazioni promozionali o newsletter senza **consenso opt-in separato** (`marketing`). Qualora tali comunicazioni fossero introdotte in futuro, l'Utente potrà revocare il consenso in qualsiasi momento senza pregiudicare il Servizio principale.

**23.4** L'Utente si impegna a mantenere aggiornati i recapiti di contatto e a verificare periodicamente la casella email (inclusa cartella spam) per le comunicazioni di Wenando.

---

### Art. 24 — Reclami, assistenza e procedura di gestione

**24.1** L'Utente consumatore che intenda presentare **reclami**, segnalazioni, richieste di chiarimento o assistenza relativa al Servizio Wenando (esclusi i rapporti diretti con le Strutture partner) può contattare:

**Email:** [hola@wenando.com](mailto:hola@wenando.com)  
**Oggetto consigliato:** «Reclamo B2C» oppure «Assistenza Wenando»

**24.2** Nella comunicazione, l'Utente è invitato a indicare:

- (a) nome e cognome e recapito di contatto;
- (b) riferimento della Richiesta di orientamento (es. `ML-####`) ove disponibile;
- (c) descrizione chiara e circostanziata del reclamo o della richiesta;
- (d) eventuale documentazione a supporto;
- (e) esito desiderato (es. rettifica dati, revoca consenso, chiarimento su matching).

**24.3** Wenando si impegna a **prendere in carico** i reclami con diligenza e a fornire una **risposta motivata** entro un termine ragionevole, di norma non superiore a **30 (trenta) giorni** lavorativi dalla ricezione, salvo complessità eccezionale comunicata all'Utente.

**24.4** Per reclami relativi a **prestazioni assistenziali**, **qualità del servizio reso dalla Struttura**, **contratti con la Struttura** o **trattamento dati post-unlock da parte del Partner**, l'Utente dovrà rivolgersi **anche o principalmente** alla Struttura partner interessata e, ove applicabile, alle autorità competenti. Wenando collaborerà nei limiti di competenza per segnalazioni relative alla Piattaforma o al trasferimento dati pre-unlock.

**24.5** La presentazione di un reclamo **non** sospende né pregiudica l'esercizio dei diritti dell'Utente di agire in sede giudiziaria o extragiudiziale, nei termini di legge.

---

### Art. 25 — Risoluzione alternativa delle controversie (ADR) e piattaforma ODR

**25.1** Ai sensi del D.Lgs. 130/2015 (attuazione della Direttiva 2013/11/UE) e del Codice del Consumo, Wenando informa l'Utente consumatore della possibilità di ricorrere a **procedure di risoluzione alternativa delle controversie (ADR)** e al **sistema europeo di risoluzione delle controversie online (ODR)**.

**25.2** **Piattaforma ODR della Commissione Europea:** l'Utente consumatore residente nell'Unione Europea può presentare un reclamo attraverso la piattaforma ODR disponibile al seguente indirizzo:

**[https://ec.europa.eu/consumers/odr](https://ec.europa.eu/consumers/odr)**

**25.3** **Recapiti Wenando per ODR:**

| Campo | Valore |
|-------|--------|
| **Email** | hola@wenando.com |
| **Sito web** | https://wenando.com |

**25.4** Wenando **non è obbligato** a partecipare a procedure ADR dinanzi a un organismo di risoluzione alternativa delle controversie consumatori, salvo obblighi di legge specifici o adesione volontaria futura. In ogni caso, Wenando valuterà le richieste dell'Utente con spirito collaborativo nei limiti di legge.

**25.5** L'Utente può inoltre rivolgersi agli **organismi di tutela del consumatore** territorialmente competenti (es. Associazioni consumeristiche, Camera di Commercio — sportelli consumatori, servizio Consumatori della propria Regione), per informazioni e assistenza nella gestione della controversia.

**25.6** Nulla nelle presenti Condizioni limita il diritto dell'Utente di adire l'**Autorità giudiziaria** competente ai sensi dell'Art. 28.

---

### Art. 26 — Diritto di recesso: non applicabilità (servizio gratuito)

**26.1** Il servizio di orientamento tramite Wenando è **gratuito** per l'Utente consumatore: **non** è previsto alcun contratto a pagamento, corrispettivo, abbonamento o prestazione a titolo oneroso tra l'Utente e Wenando per l'utilizzo del Wizard, la visualizzazione dei risultati di matching o l'accesso alle funzionalità B2C descritte nelle presenti Condizioni.

**26.2** Di conseguenza, il **diritto di recesso** di cui agli **artt. 52 e seguenti del Codice del Consumo** (in materia di contratti a distanza e contratti fuori sede) **non si applica** al rapporto tra l'Utente e Wenando, in quanto:

- (a) **manca una prestazione a titolo oneroso** da parte di Wenando verso il consumatore per il Servizio B2C di orientamento;
- (b) il Servizio gratuito non configura un «contratto di vendita» o «contratto di fornitura di servizi» a pagamento nei confronti di Wenando ai fini del recesso consumeristico;
- (c) l'Utente non versa alcun prezzo, tariffa o contraprestazione economica a Wenando per l'utilizzo del Wizard nella versione 1.0.0.

**26.3** **Pagamenti per servizi assistenziali:** eventuali pagamenti, caparre, canoni, rette o corrispettivi per servizi di assistenza, ospitalità, degenza o cure si regolano **esclusivamente** nel rapporto diretto **Utente–Struttura partner**, secondo le condizioni commerciali, contrattuali e di recesso eventualmente offerte dalla Struttura e applicabili per legge. Wenando **non** è parte di tali rapporti e **non** gestisce pagamenti B2C per prestazioni assistenziali nella versione 1.0.0.

**26.4** **Servizi futuri a pagamento:** qualora Wenando offrisse in futuro servizi a pagamento direttamente ai consumatori (es. premium features, consulenze a tariffa), verranno pubblicate **condizioni specifiche**, **informative precontrattuali** conformi agli artt. 49 e ss. del Codice del Consumo, indicazione chiara del prezzo, e — ove applicabile — **diritto di recesso** con modalità e termini di esercizio (14 giorni o altro termine di legge).

**26.5** L'Utente può in ogni caso **revocare i consensi** al trattamento dati e richiedere la **cancellazione** della Richiesta di orientamento nei limiti dell'[Informativa Privacy](./PRIVACY_POLICY.md), fermo restando che ciò **non** equivale al recesso da un contratto a pagamento con Wenando, bensì all'esercizio dei diritti GDPR.

---

## PARTE VI — MODIFICHE, LEGGE APPLICABILE E DISPOSIZIONI FINALI

### Art. 27 — Modifiche alle Condizioni

**27.1** Wenando si riserva il diritto di **modificare, integrare o aggiornare** le presenti Condizioni per:

- (a) adeguamento a modifiche normative o regolamentari (nazionali, europee);
- (b) evoluzione tecnica o funzionale del Servizio;
- (c) esigenze di sicurezza, prevenzione abusi o tutela degli Utenti;
- (d) chiarimenti redazionali non sostanziali;
- (e) introduzione di nuove funzionalità o servizi.

**27.2** Le modifiche saranno pubblicate su **[https://wenando.com/terms](https://wenando.com/terms)** (o URL equivalente) con indicazione della **nuova versione** (schema semver, es. `1.1.0`, `2.0.0`) e della **data di entrata in vigore**.

**27.3** Ove le modifiche siano **sostanziali** e incidano su diritti o obblighi dell'Utente, Wenando provvederà a comunicarle con **preavviso ragionevole**, di norma non inferiore a **15 (quindici) giorni** prima dell'efficacia, mediante:

- avviso prominente sul sito web;
- email all'Utente registrato, ove disponibile;
- richiesta di nuova accettazione esplicita per l'invio di nuove Richieste di orientamento.

**27.4** L'**uso continuato** del Servizio dopo la decorrenza della nuova versione, unitamente all'accettazione registrata ove richiesta, costituisce accettazione delle Condizioni aggiornate, **salvo diritti inderogabili del consumatore**.

**27.5** Per le Richieste di orientamento già inviate prima della modifica, si applicano le Condizioni in vigore al momento dell'invio, salvo che la modifica sia imposta da legge o migliori la tutela dell'Utente.

**27.6** Le versioni precedenti delle Condizioni possono essere richieste a **hola@wenando.com** per finalità di consultazione storica, ove archiviate.

---

### Art. 28 — Legge applicabile e foro competente

**28.1** Le presenti Condizioni sono regolate dalla **legge italiana**, con esclusione delle norme di diritto internazionale privato che rinviano ad altra legge, salvo disposizioni inderogabili a tutela del consumatore.

**28.2** **Foro del consumatore.** Per ogni controversia derivante dalle presenti Condizioni o connessa all'utilizzo del Servizio Wenando, qualora l'Utente agisca in qualità di **consumatore** (persona fisica che agisce per scopi estranei all'attività imprenditoriale, commerciale, artigianale o professionale eventualmente svolta), è competente in via **esclusiva** — nei limiti e alle condizioni di legge — il **foro del luogo di domicilio o residenza del consumatore**, come previsto dall'**art. 66-bis del Codice di procedura civile** e dall'**art. 18, comma 1, del Codice del Consumo**, nonché dal **Regolamento (UE) n. 1215/2012** (Bruxelles I bis) in materia di competenza giurisdizionale nelle controversie con consumatori.

**28.3** **Clausola di foro alternativo.** Qualsiasi clausola che preveda foro esclusivo a favore di Wenando in danno del consumatore è **inefficace** nei rapporti B2C, salvo quanto consentito dalla legge a tutela del professionista in casi specifici non applicabili al presente Servizio gratuito B2C.

**28.4** **Composizione extragiudiziale.** Prima di adire l'autorità giudiziaria, le Parti possono tentare una composizione bonaria mediante contatto a **hola@wenando.com** o ricorso alle procedure ADR/ODR di cui all'Art. 25.

**28.5** **Lingua del contratto.** Il testo italiano delle presenti Condizioni fa **fede** ai fini giuridici. Eventuali traduzioni in altre lingue hanno **solo scopo informativo** e non producono effetti contrattuali in caso di discordanza.

---

### Art. 29 — Disposizioni finali e clausole generali

**29.1** **Intero accordo.** Per quanto concerne il Servizio B2C gratuito di orientamento, le presenti Condizioni, unitamente all'[Informativa sulla Privacy](./PRIVACY_POLICY.md) e alla [Cookie Policy](./COOKIE_POLICY.md), costituiscono l'**intero accordo** tra l'Utente e Wenando su tali aspetti, e sostituiscono ogni precedente intesa orale o scritta relativa allo stesso oggetto.

**29.2** **Nullità parziale.** Qualora una o più clausole delle presenti Condizioni fossero dichiarate nulle, invalide o inefficaci da un'autorità competente, tale nullità **non** comporta la nullità dell'intero Contratto. Le restanti clausole resteranno **pienamente efficaci**, salvo che la clausola nulla costituisca condizione essenziale del Contratto. Le Parti sostituiranno la clausola nulla con una disposizione valida che realizzi, per quanto possibile, l'economia della clausola invalida nel rispetto della legge.

**29.3** **Rinuncia.** Il mancato esercizio o ritardo nell'esercizio di un diritto o facoltà spettante a Wenando ai sensi delle presenti Condizioni **non** costituisce rinuncia a tale diritto, né preclude l'esercizio successivo.

**29.4** **Cessione del contratto.** Wenando può cedere o trasferire il Contratto, in tutto o in parte, a società controllanti, controllate o affiliate, o in caso di fusione, scissione o cessione d'azienda, previa comunicazione all'Utente ove richiesto dalla legge. L'Utente consumatore **non** può cedere il Contratto senza consenso scritto di Wenando.

**29.5** **Forza maggiore.** Wenando non è responsabile per inadempimenti o ritardi causati da eventi di forza maggiore o caso fortuito (art. 1218 c.c.), inclusi a titolo esemplificativo: calamità naturali, guerre, atti terroristici, pandemie, interruzioni generalizzate di energia o rete, provvedimenti dell'autorità, scioperi di terze parti, attacchi informatici su larga scala non imputabili a negligenza grave di Wenando.

**29.6** **Sopravvivenza.** Gli articoli che per natura devono sopravvivere alla cessazione del rapporto (limitazioni di responsabilità nei limiti di legge, proprietà intellettuale, legge applicabile, foro, risoluzione controversie, manleva) continueranno ad applicarsi.

**29.7** **Comunicazioni formali.** Salvo diversa indicazione, le comunicazioni formali ai sensi delle presenti Condizioni possono essere effettuate via email a **hola@wenando.com** (Wenando) o all'ultimo indirizzo email fornito dall'Utente.

---

## PARTE VII — RIFERIMENTI INCROCIATI E VERSIONING

### Art. 30 — Documenti collegati e riferimenti normativi

**30.1** I seguenti documenti costituiscono riferimento integrativo e sono consultabili ai link indicati:

| Documento | Percorso / URL | Oggetto |
|-----------|----------------|---------|
| **Informativa sulla Privacy** | [./PRIVACY_POLICY.md](./PRIVACY_POLICY.md) · [https://wenando.com/privacy](https://wenando.com/privacy) | Trattamento dati personali, diritti GDPR, retention |
| **Cookie Policy** | [./COOKIE_POLICY.md](./COOKIE_POLICY.md) · [https://wenando.com/cookies](https://wenando.com/cookies) | Cookie e tecnologie simili, consenso analytics |
| **Condizioni Partner B2B** | [./TERMS_B2B_PARTNERS.md](./TERMS_B2B_PARTNERS.md) | Marketplace, unlock, obblighi Partner, Scenario A |
| **Piano Conformità Legale** | Documento interno Wenando (non pubblicato) | Architettura privacy, consensi, minimizzazione |

**30.2** **Normativa di riferimento** (elenco non esaustivo):

- D.Lgs. 6 settembre 2005, n. 206 (Codice del Consumo);
- D.Lgs. 9 aprile 2003, n. 70 (commercio elettronico);
- D.Lgs. 130/2015 (ADR/ODR);
- Regolamento (UE) 2016/679 (GDPR);
- D.Lgs. 196/2003 e D.Lgs. 101/2018 (Codice Privacy);
- Regolamento (UE) n. 1215/2012 (competenza giurisdizionale);
- Codice civile (libro IV, titolo II — contratti in generale);
- Normativa sui servizi della società dell'informazione e contenuti digitali ove applicabile.

---

### Art. 31 — Storico versioni

| Versione | Data | Note |
|----------|------|------|
| **1.0.0** | **Giugno 2026** | Prima emissione — go-live Piattaforma Wenando B2C |

---

## DICHIARAZIONE DI NON CONSULENZA LEGALE

*Le presenti Condizioni Generali di Utilizzo sono state redatte sulla base delle specifiche tecniche, del modello di business e del piano di conformità della Piattaforma Wenando. **Non costituiscono consulenza legale** né sostituiscono il parere di un avvocato o di un professionista qualificato. Il Fornitore raccomanda la **revisione legale professionale** da parte di un legale abilitato prima dell'utilizzo in produzione, in particolare per quanto concerne clausole di limitazione di responsabilità, conformità al Codice del Consumo, applicabilità della normativa sui contenuti digitali e adeguatezza delle informative precontrattuali.*

---

**Julian Rovera — Wenando**  
[hola@wenando.com](mailto:hola@wenando.com) · [https://wenando.com](https://wenando.com) · P.IVA IT13227980011

**Versione documento: 1.0.0 — Giugno 2026**
