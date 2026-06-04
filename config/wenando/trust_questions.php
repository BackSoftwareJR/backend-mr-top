<?php

declare(strict_types=1);

/**
 * Pre-defined Trust Test questions per operational sector (onboarding dynamic.sector).
 * Answers are stored as option values in trust_tests.answers JSON.
 */
return [
    'rsa' => [
        [
            'id' => 'emergency_night',
            'title' => 'Emergenza notturna',
            'prompt' => 'Come viene gestita un\'emergenza medica notturna in struttura?',
            'type' => 'radio',
            'options' => [
                ['value' => 'nurse_h24_med_oncall', 'label' => 'Infermiere H24 + medico reperibile con intervento entro 15 min'],
                ['value' => 'nurse_h24_118', 'label' => 'Infermiere H24, attivazione 118 e familiari entro 30 min'],
                ['value' => 'guard_med_weekend', 'label' => 'Guardia medica notturna solo weekend, infermiere in settimana'],
                ['value' => 'no_night_staff', 'label' => 'Nessun presidio sanitario notturno dedicato'],
            ],
        ],
        [
            'id' => 'fall_protocol',
            'title' => 'Caduta ospite',
            'prompt' => 'Primo intervento obbligatorio in caso di caduta alle 02:00?',
            'type' => 'radio',
            'options' => [
                ['value' => 'assess_no_move', 'label' => 'Valutazione infermieristica senza spostare + medico + scheda incidente'],
                ['value' => 'move_if_ok', 'label' => 'Sollevamento immediato se l\'ospite è cosciente'],
                ['value' => 'family_first', 'label' => 'Contatto familiare prima della valutazione clinica'],
                ['value' => 'no_written', 'label' => 'Intervento senza documentazione scritta'],
            ],
        ],
        [
            'id' => 'family_contact',
            'title' => 'Comunicazione familiari',
            'prompt' => 'Canale per richieste urgenti dei familiari fuori orario?',
            'type' => 'radio',
            'options' => [
                ['value' => 'h24_line_30m', 'label' => 'Linea dedicata H24 con risposta entro 30 minuti'],
                ['value' => 'whatsapp_coordinator', 'label' => 'WhatsApp coordinatore con SLA entro 1 ora'],
                ['value' => 'email_next_day', 'label' => 'Email con risposta il giorno lavorativo successivo'],
                ['value' => 'reception_only', 'label' => 'Solo reception in orario di ufficio'],
            ],
        ],
        [
            'id' => 'quality_metric',
            'title' => 'Qualità del servizio',
            'prompt' => 'Metrica principale per monitorare la qualità assistenziale?',
            'type' => 'select',
            'options' => [
                ['value' => 'nps_quarterly', 'label' => 'NPS familiari trimestrale'],
                ['value' => 'internal_audit', 'label' => 'Audit interni mensili su procedure'],
                ['value' => 'adverse_events', 'label' => 'Registro eventi avversi e tempi di risposta'],
                ['value' => 'no_metric', 'label' => 'Nessuna metrica strutturata'],
            ],
        ],
        [
            'id' => 'medication',
            'title' => 'Somministrazione farmaci',
            'prompt' => 'Chi verifica la terapia farmacologica quotidiana?',
            'type' => 'radio',
            'options' => [
                ['value' => 'nurse_double_check', 'label' => 'Infermiere con doppio controllo e registro cartaceo/digitale'],
                ['value' => 'nurse_only', 'label' => 'Solo infermiere senza doppio controllo'],
                ['value' => 'oss', 'label' => 'OSS con supervisione infermieristica'],
                ['value' => 'family', 'label' => 'Familiari portano farmaci autonomamente'],
            ],
        ],
        [
            'id' => 'infection_control',
            'title' => 'Controllo infezioni',
            'prompt' => 'Protocollo in caso di focolaio infettivo (es. gastroenterite)?',
            'type' => 'radio',
            'options' => [
                ['value' => 'isolation_asl', 'label' => 'Isolamento + ASL + tracciamento contatti'],
                ['value' => 'isolation_internal', 'label' => 'Isolamento interno senza segnalazione ASL'],
                ['value' => 'enhanced_hygiene', 'label' => 'Solo igienizzazione rafforzata'],
                ['value' => 'no_protocol', 'label' => 'Nessun protocollo formalizzato'],
            ],
        ],
        [
            'id' => 'staff_training',
            'title' => 'Formazione personale',
            'prompt' => 'Frequenza aggiornamento BLSD / emergenze per il personale?',
            'type' => 'select',
            'options' => [
                ['value' => 'annual', 'label' => 'Annuale obbligatoria per tutto il personale'],
                ['value' => 'biannual', 'label' => 'Ogni 2 anni'],
                ['value' => 'on_hire', 'label' => 'Solo all\'assunzione'],
                ['value' => 'voluntary', 'label' => 'Formazione volontaria'],
            ],
        ],
        [
            'id' => 'dementia_care',
            'title' => 'Ospiti con demenza',
            'prompt' => 'Approccio per ospiti con demenza e rischio vagabondaggio?',
            'type' => 'radio',
            'options' => [
                ['value' => 'secured_areas', 'label' => 'Aree protette + personale formato + monitoraggio'],
                ['value' => 'alarm_bed', 'label' => 'Solo allarmi letto/sedia'],
                ['value' => 'generic_supervision', 'label' => 'Supervisione generica senza piano individualizzato'],
                ['value' => 'not_accepted', 'label' => 'Non accettiamo ospiti con demenza avanzata'],
            ],
        ],
    ],

    'adi' => [
        [
            'id' => 'visit_scheduling',
            'title' => 'Pianificazione visite',
            'prompt' => 'Come garantite la puntualità delle visite domiciliari?',
            'type' => 'radio',
            'options' => [
                ['value' => 'gps_routing', 'label' => 'Pianificazione con routing GPS e backup operatore'],
                ['value' => 'fixed_slots', 'label' => 'Fasce orarie fisse senza backup'],
                ['value' => 'on_call', 'label' => 'Intervento on-call senza slot garantiti'],
                ['value' => 'manual', 'label' => 'Assegnazione manuale giorno per giorno'],
            ],
        ],
        [
            'id' => 'emergency_home',
            'title' => 'Emergenza a domicilio',
            'prompt' => 'Procedura se l\'assistito ha un crollo pressorio durante la visita?',
            'type' => 'radio',
            'options' => [
                ['value' => '118_family_protocol', 'label' => 'Protocollo 118 + medico + familiari + scheda evento'],
                ['value' => '118_only', 'label' => 'Chiamata 118 senza follow-up strutturato'],
                ['value' => 'family_decides', 'label' => 'Decisione affidata al familiare presente'],
                ['value' => 'no_protocol', 'label' => 'Nessun protocollo scritto'],
            ],
        ],
        [
            'id' => 'care_plan',
            'title' => 'Piano assistenziale',
            'prompt' => 'Frequenza di revisione del piano assistenziale individualizzato?',
            'type' => 'select',
            'options' => [
                ['value' => 'monthly', 'label' => 'Mensile o al cambio condizioni'],
                ['value' => 'quarterly', 'label' => 'Trimestrale'],
                ['value' => 'on_request', 'label' => 'Solo su richiesta familiare'],
                ['value' => 'no_plan', 'label' => 'Nessun piano formalizzato'],
            ],
        ],
        [
            'id' => 'operator_substitution',
            'title' => 'Sostituzione operatori',
            'prompt' => 'Cosa succede se l\'operatore abituale non può presentarsi?',
            'type' => 'radio',
            'options' => [
                ['value' => 'briefed_substitute', 'label' => 'Sostituto informato sul piano + famiglia avvisata'],
                ['value' => 'any_available', 'label' => 'Qualsiasi operatore disponibile senza briefing'],
                ['value' => 'skip_visit', 'label' => 'Visita saltata e recuperata quando possibile'],
                ['value' => 'family_covers', 'label' => 'Famiglia copre autonomamente'],
            ],
        ],
        [
            'id' => 'medication_adi',
            'title' => 'Gestione terapie',
            'prompt' => 'Chi somministra i farmaci a domicilio?',
            'type' => 'radio',
            'options' => [
                ['value' => 'nurse_only', 'label' => 'Solo infermiere abilitati'],
                ['value' => 'oss_supervised', 'label' => 'OSS con supervisione infermieristica'],
                ['value' => 'family_with_log', 'label' => 'Familiari con registro firmato'],
                ['value' => 'self_admin', 'label' => 'Autonomia dell\'assistito'],
            ],
        ],
        [
            'id' => 'privacy_home',
            'title' => 'Privacy domicilio',
            'prompt' => 'Come proteggete i dati sanitari in ambiente domiciliare?',
            'type' => 'radio',
            'options' => [
                ['value' => 'encrypted_app', 'label' => 'App cifrata + formazione GDPR operatori'],
                ['value' => 'paper_locked', 'label' => 'Cartaceo in armadio chiuso'],
                ['value' => 'personal_devices', 'label' => 'Dispositivi personali operatori'],
                ['value' => 'verbal_only', 'label' => 'Comunicazione solo verbale'],
            ],
        ],
        [
            'id' => 'night_coverage',
            'title' => 'Copertura notturna',
            'prompt' => 'Servizio notturno / reperibilità per urgenze ADI?',
            'type' => 'select',
            'options' => [
                ['value' => 'h24_coordinator', 'label' => 'Coordinatore reperibile H24'],
                ['value' => 'night_shift', 'label' => 'Turni notturni programmati'],
                ['value' => 'day_only', 'label' => 'Solo diurno, 118 per emergenze'],
                ['value' => 'none', 'label' => 'Nessuna copertura notturna'],
            ],
        ],
        [
            'id' => 'quality_adi',
            'title' => 'Controllo qualità',
            'prompt' => 'Come verificate la qualità degli interventi domiciliari?',
            'type' => 'radio',
            'options' => [
                ['value' => 'spot_checks', 'label' => 'Controlli a sorpresa + feedback familiare'],
                ['value' => 'family_survey', 'label' => 'Solo questionari familiari'],
                ['value' => 'operator_self', 'label' => 'Autocertificazione operatori'],
                ['value' => 'no_control', 'label' => 'Nessun controllo sistematico'],
            ],
        ],
    ],

    'centro' => [
        [
            'id' => 'admission_criteria',
            'title' => 'Criteri di ammissione',
            'prompt' => 'Valutazione prima dell\'ingresso al centro diurno?',
            'type' => 'radio',
            'options' => [
                ['value' => 'multidisciplinary', 'label' => 'Valutazione multidisciplinare con piano personalizzato'],
                ['value' => 'social_only', 'label' => 'Solo valutazione assistente sociale'],
                ['value' => 'self_referral', 'label' => 'Iscrizione diretta senza valutazione'],
                ['value' => 'family_decides', 'label' => 'Decide esclusivamente la famiglia'],
            ],
        ],
        [
            'id' => 'transport',
            'title' => 'Trasporto',
            'prompt' => 'Servizio navetta per raggiungere il centro?',
            'type' => 'radio',
            'options' => [
                ['value' => 'door_to_door', 'label' => 'Navetta porta a porta con accompagnatore'],
                ['value' => 'fixed_stops', 'label' => 'Navetta con fermate fisse'],
                ['value' => 'family_transport', 'label' => 'Solo trasporto familiare'],
                ['value' => 'public', 'label' => 'Mezzi pubblici senza assistenza'],
            ],
        ],
        [
            'id' => 'activities',
            'title' => 'Attività riabilitative',
            'prompt' => 'Programma attività per ospiti con disabilità cognitive?',
            'type' => 'select',
            'options' => [
                ['value' => 'individual_plan', 'label' => 'Piano individualizzato con educatori dedicati'],
                ['value' => 'group_generic', 'label' => 'Attività di gruppo generiche'],
                ['value' => 'optional', 'label' => 'Attività opzionali su richiesta'],
                ['value' => 'none', 'label' => 'Nessuna attività strutturata'],
            ],
        ],
        [
            'id' => 'lunch_diet',
            'title' => 'Pasti e diete',
            'prompt' => 'Gestione diete speciali e allergie?',
            'type' => 'radio',
            'options' => [
                ['value' => 'dietician_plan', 'label' => 'Piano dietetico con dietista + etichettatura piatti'],
                ['value' => 'kitchen_staff', 'label' => 'Cucina informata senza piano scritto'],
                ['value' => 'family_brings', 'label' => 'Famiglia porta il pranzo'],
                ['value' => 'standard_menu', 'label' => 'Menu unico per tutti'],
            ],
        ],
        [
            'id' => 'staff_ratio',
            'title' => 'Rapporto operatori/ospiti',
            'prompt' => 'Rapporto medio operatori : ospiti durante le attività?',
            'type' => 'select',
            'options' => [
                ['value' => '1_5', 'label' => '1:5 o migliore'],
                ['value' => '1_8', 'label' => '1:6 – 1:8'],
                ['value' => '1_12', 'label' => '1:9 – 1:12'],
                ['value' => 'over_12', 'label' => 'Oltre 1:12'],
            ],
        ],
        [
            'id' => 'emergency_centro',
            'title' => 'Emergenze in centro',
            'prompt' => 'Primo intervento per malore in sala comune?',
            'type' => 'radio',
            'options' => [
                ['value' => 'blsd_118', 'label' => 'BLSD personale + 118 + familiari + scheda'],
                ['value' => '118_wait', 'label' => 'Attesa 118 senza manovre'],
                ['value' => 'family_pickup', 'label' => 'Famiglia ritira e porta in PS'],
                ['value' => 'no_trained', 'label' => 'Nessun personale formato BLSD'],
            ],
        ],
        [
            'id' => 'family_communication',
            'title' => 'Aggiornamenti familiari',
            'prompt' => 'Frequenza report ai familiari sull\'andamento giornaliero?',
            'type' => 'radio',
            'options' => [
                ['value' => 'daily_digital', 'label' => 'Report digitale giornaliero'],
                ['value' => 'weekly_call', 'label' => 'Telefonata settimanale'],
                ['value' => 'on_incident', 'label' => 'Solo in caso di incidenti'],
                ['value' => 'never', 'label' => 'Nessun aggiornamento strutturato'],
            ],
        ],
        [
            'id' => 'quality_centro',
            'title' => 'Standard qualità',
            'prompt' => 'Certificazione o standard di riferimento del centro?',
            'type' => 'select',
            'options' => [
                ['value' => 'accredited', 'label' => 'Accreditamento regionale / ISO'],
                ['value' => 'internal_standards', 'label' => 'Standard interni documentati'],
                ['value' => 'informal', 'label' => 'Buone pratiche informali'],
                ['value' => 'none', 'label' => 'Nessuno standard dichiarato'],
            ],
        ],
    ],

    'clinica' => [
        [
            'id' => 'appointment_triage',
            'title' => 'Triage appuntamenti',
            'prompt' => 'Come gestite un paziente con sintomi urgenti in ambulatorio?',
            'type' => 'radio',
            'options' => [
                ['value' => 'triage_nurse', 'label' => 'Triage infermieristico + priorizzazione + medico'],
                ['value' => 'fifo', 'label' => 'Ordine di arrivo senza triage'],
                ['value' => 'redirect_er', 'label' => 'Reindirizzamento immediato al PS'],
                ['value' => 'wait_slot', 'label' => 'Attesa prossimo slot libero'],
            ],
        ],
        [
            'id' => 'sterilization',
            'title' => 'Sterilizzazione',
            'prompt' => 'Protocollo sterilizzazione strumenti invasivi?',
            'type' => 'radio',
            'options' => [
                ['value' => 'tracked_cycle', 'label' => 'Cicli tracciati con registro e controlli periodici'],
                ['value' => 'single_use', 'label' => 'Monouso dove possibile, resto in autoclave'],
                ['value' => 'external', 'label' => 'Sterilizzazione esterna senza tracciamento interno'],
                ['value' => 'informal', 'label' => 'Procedure non documentate'],
            ],
        ],
        [
            'id' => 'clinical_records',
            'title' => 'Cartella clinica',
            'prompt' => 'Conservazione cartelle cliniche e referti?',
            'type' => 'select',
            'options' => [
                ['value' => 'ehr_encrypted', 'label' => 'Fascicolo elettronico cifrato con backup'],
                ['value' => 'paper_archive', 'label' => 'Cartaceo in archivio protetto'],
                ['value' => 'mixed_no_backup', 'label' => 'Misto senza backup regolare'],
                ['value' => 'personal_files', 'label' => 'File su PC medici senza policy'],
            ],
        ],
        [
            'id' => 'informed_consent',
            'title' => 'Consenso informato',
            'prompt' => 'Procedura consenso informato prima di procedure?',
            'type' => 'radio',
            'options' => [
                ['value' => 'written_signed', 'label' => 'Modulo scritto firmato e archiviato'],
                ['value' => 'verbal_noted', 'label' => 'Consenso verbale annotato in cartella'],
                ['value' => 'implied', 'label' => 'Consenso implicito alla prenotazione'],
                ['value' => 'none', 'label' => 'Nessuna procedura formalizzata'],
            ],
        ],
        [
            'id' => 'lab_samples',
            'title' => 'Campioni laboratorio',
            'prompt' => 'Tracciabilità prelievi e campioni biologici?',
            'type' => 'radio',
            'options' => [
                ['value' => 'barcode_chain', 'label' => 'Barcode + catena di custodia documentata'],
                ['value' => 'label_manual', 'label' => 'Etichettatura manuale'],
                ['value' => 'lab_responsible', 'label' => 'Responsabilità del laboratorio esterno'],
                ['value' => 'no_trace', 'label' => 'Nessuna tracciabilità interna'],
            ],
        ],
        [
            'id' => 'privacy_clinic',
            'title' => 'Privacy ambulatorio',
            'prompt' => 'Come garantite la privacy durante le visite?',
            'type' => 'radio',
            'options' => [
                ['value' => 'private_rooms', 'label' => 'Ambulatori dedicati + policy accessi'],
                ['value' => 'curtain', 'label' => 'Paravento / tende in sala condivisa'],
                ['value' => 'shared_open', 'label' => 'Visite in open space'],
                ['value' => 'no_policy', 'label' => 'Nessuna policy privacy'],
            ],
        ],
        [
            'id' => 'continuing_education',
            'title' => 'Aggiornamento professionale',
            'prompt' => 'ECM / formazione continua del personale sanitario?',
            'type' => 'select',
            'options' => [
                ['value' => 'mandatory_tracking', 'label' => 'Obbligatorio con registro ECM'],
                ['value' => 'encouraged', 'label' => 'Incentivato ma non tracciato'],
                ['value' => 'individual', 'label' => 'A carico del singolo professionista'],
                ['value' => 'none', 'label' => 'Non previsto'],
            ],
        ],
        [
            'id' => 'complaints',
            'title' => 'Reclami pazienti',
            'prompt' => 'Gestione reclami e feedback negativi?',
            'type' => 'radio',
            'options' => [
                ['value' => 'formal_sla', 'label' => 'Procedura scritta con SLA e responsabile'],
                ['value' => 'informal_manager', 'label' => 'Gestione informale dal direttore'],
                ['value' => 'email_only', 'label' => 'Solo casella email senza follow-up'],
                ['value' => 'ignore', 'label' => 'Nessuna gestione strutturata'],
            ],
        ],
    ],
];
