# Diagrammes du projet VAS CDR

Ce document regroupe les principaux diagrammes proposes pour le rapport du projet.
Les diagrammes sont ecrits avec Mermaid et peuvent etre colles dans Mermaid Live Editor,
VS Code, GitHub ou un outil compatible Mermaid.

## 1. Diagramme de cas d'utilisation

```mermaid
flowchart LR
    Admin[Administrateur]
    Business[Analyste Business]
    Ops[Analyste Operationnel]

    subgraph System[Plateforme d'analyse des CDR - VAS]
        UC1((Consulter dashboard))
        UC2((Rechercher par MSISDN))
        UC3((Importer liste MSISDN))
        UC4((Consulter alertes))
        UC5((Gerer utilisateurs))
        UC6((Gerer FTP))
        UC7((Gerer BD et Scheduler))
        UC8((Pause / demarrer ETL))
        UC9((Nettoyer donnees anciennes))
        UC10((Suivre loading CDR))
        UC11((Suivre agregation))
        UC12((Gerer services))
        UC13((Gerer fournisseurs))
    end

    Business --> UC1
    Business --> UC2
    Business --> UC3
    Business --> UC4

    Ops --> UC1
    Ops --> UC10
    Ops --> UC11
    Ops --> UC12
    Ops --> UC13

    Admin --> UC1
    Admin --> UC5
    Admin --> UC6
    Admin --> UC7
    Admin --> UC8
    Admin --> UC9
```

## 2. Diagramme de sequence - ETL CDR

```mermaid
sequenceDiagram
    actor Admin as Administrateur
    actor Ops as Operationnel
    participant Scheduler as Laravel Scheduler
    participant Import as Commande cdr:import
    participant Incoming as Stockage incoming
    participant TMP as Oracle TMP
    participant DETAIL as Oracle DETAIL
    participant AGG as Oracle AGG
    participant Files as processed / error
    participant Logs as Logs

    Admin->>Scheduler: Configurer planification
    Admin->>Import: Pause / demarrer ETL
    Ops->>Incoming: Surveiller fichiers MMG/OCC

    Scheduler->>Import: Lancer cdr:import all
    Import->>Import: Verifier etat ETL

    alt ETL en pause
        Import->>Logs: Enregistrer import ignore
    else ETL actif
        Import->>Incoming: Lire fichiers MMG/OCC
        Import->>TMP: Inserer donnees brutes
        TMP->>DETAIL: Transformer et nettoyer
        DETAIL->>AGG: Agreger par date, heure, service, type

        alt Import OK
            Import->>Files: Deplacer fichier vers processed
            Import->>Logs: Enregistrer succes
        else Erreur import
            Import->>TMP: Rollback transaction
            Import->>Files: Deplacer fichier vers error
            Import->>Logs: Enregistrer erreur
        end
    end
```

## 3. Diagramme d'activite - Traitement ETL

```mermaid
flowchart TD
    A([Debut]) --> B{Nouveaux fichiers CDR ?}
    B -- Non --> Z([Fin])
    B -- Oui --> C[Lire fichier CSV]
    C --> D[Inserer lignes dans TMP]
    D --> E[Transformer vers DETAIL]
    E --> F[Construire agregations AGG]
    F --> G{Traitement reussi ?}
    G -- Oui --> H[Deplacer fichier vers processed]
    G -- Non --> I[Rollback transaction]
    I --> J[Deplacer fichier vers error]
    H --> K[Enregistrer logs]
    J --> K
    K --> L{Autres fichiers ?}
    L -- Oui --> C
    L -- Non --> Z
```

## 4. Diagramme d'architecture

```mermaid
flowchart LR
    Browser[Navigateur utilisateur]
    Laravel[Application Laravel]
    Auth[Middleware Auth / Roles]
    Oracle[(Oracle Database)]
    Storage[(Storage local CDR)]
    FTP[Serveur FTP]
    Scheduler[Laravel Scheduler]
    Windows[Windows Task Scheduler]

    Browser --> Laravel
    Laravel --> Auth
    Laravel --> Oracle
    Laravel --> Storage
    Laravel --> FTP

    Windows --> Scheduler
    Scheduler --> Laravel
    Scheduler --> Storage
    Scheduler --> Oracle

    subgraph Modules[Modules Laravel]
        Dashboard[Dashboard]
        Search[Recherche MSISDN]
        Loading[Loading CDR]
        Agg[Agregation]
        AdminBD[Gerer BD]
        AdminFTP[Gerer FTP]
        Users[Gerer utilisateurs]
    end

    Laravel --> Modules
```

## 5. Diagramme de base de donnees simplifie

```mermaid
erDiagram
    USERS {
        int id
        string name
        string email
        string password
        string role
        string direction
        string tel
    }

    SERVICE_PROVIDER {
        int id
        string provider_name
        string nationnalite
        string id_fiscale
        string adresse
    }

    SERVICES {
        int id
        string service_name
        string short_code
        string keyword
        string type
        decimal price
        int provider_id
    }

    RA_T_TMP_MMG {
        string filename
        string a_msisdn
        string b_msisdn
        string event_type
        string call_type
        string service_type
        string orig_start_time
    }

    RA_T_DETAIL_MMG {
        string filename
        string a_msisdn
        string b_msisdn
        string event_type
        string call_type
        string service_type
        date start_date
        int start_hour
    }

    RA_T_AGG_MMG {
        string filename
        string b_msisdn
        date start_date
        int start_hour
        string event_type
        string call_type
        int cdr_count
    }

    RA_T_TMP_OCC {
        string filename
        string a_msisdn
        string b_msisdn
        string call_type
        string event_type
        string partner
        string charge_amount_orig
    }

    RA_T_DETAIL_OCC {
        string filename
        string a_msisdn
        string b_msisdn
        string call_type
        string event_type
        string partner
        decimal charge_amount_orig
    }

    RA_T_AGG_OCC {
        string keyword
        string b_msisdn
        date start_date
        int start_hour
        string call_type
        string event_type
        int cdr_count
        decimal charge_amount
    }

    SERVICE_PROVIDER ||--o{ SERVICES : fournit
    RA_T_TMP_MMG ||--o{ RA_T_DETAIL_MMG : transforme
    RA_T_DETAIL_MMG ||--o{ RA_T_AGG_MMG : agrege
    RA_T_TMP_OCC ||--o{ RA_T_DETAIL_OCC : transforme
    RA_T_DETAIL_OCC ||--o{ RA_T_AGG_OCC : agrege
```

## 6. Diagramme de composants

```mermaid
flowchart TB
    subgraph UI[Interface Web Laravel]
        Login[Authentification]
        Dashboard[Dashboard]
        Business[Recherche MSISDN]
        Operations[Loading CDR et Agregation]
        Admin[Administration]
    end

    subgraph Backend[Backend Laravel]
        Controllers[Controllers]
        Middleware[Middleware roles]
        Commands[Commandes Artisan]
        Scheduler[Laravel Scheduler]
        StorageService[Gestion fichiers CDR]
    end

    subgraph Data[Donnees]
        Oracle[(Oracle)]
        Files[(incoming / processed / error)]
        Logs[(logs)]
    end

    UI --> Controllers
    Controllers --> Middleware
    Controllers --> Oracle
    Controllers --> Files

    Scheduler --> Commands
    Commands --> StorageService
    Commands --> Oracle
    Commands --> Files
    Commands --> Logs
```

## 7. Diagramme d'etat des fichiers CDR

```mermaid
stateDiagram-v2
    [*] --> Nouveau
    Nouveau --> Incoming: fichier disponible
    Incoming --> EnTraitement: cdr:import

    EnTraitement --> Processed: import OK
    EnTraitement --> Error: erreur import

    Processed --> Supprime: retention 30 jours
    Error --> Analyse: consultation erreur
    Analyse --> Incoming: correction / relance
    Supprime --> [*]
```

