@extends('layouts.app')

@section('title', 'Gestion FTP')
@section('page-title', 'Gestion FTP')

@push('styles')
    <style>
        .ftp-page {
            color: #0f172a;
        }

        .ftp-card-title {
            color: #123b7a;
            font-size: 1rem;
            font-weight: 750;
        }

        .ftp-form-label {
            color: #10213f;
            font-size: .86rem;
            font-weight: 650;
        }

        .ftp-muted-control {
            background: #f8fafc;
            color: #334155;
        }

        .ftp-status-box {
            border: 1px solid #86efac;
            background: #f0fdf4;
            border-radius: 8px;
            padding: 1rem;
        }

        .ftp-status-icon {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #22c55e;
            color: #fff;
            flex: 0 0 auto;
        }

        .ftp-table-actions .btn {
            width: 34px;
            height: 30px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .ftp-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .7rem;
            align-items: center;
            justify-content: space-between;
        }

        .ftp-toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .ftp-search {
            max-width: 330px;
        }

        @media (max-width: 767.98px) {
            .ftp-search {
                max-width: 100%;
                width: 100%;
            }

            .ftp-toolbar-actions,
            .ftp-toolbar-actions .btn {
                width: 100%;
            }
        }
    </style>
@endpush

@section('content')
    <div class="ftp-page">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
            <div>
                <h2 class="fs-4 fw-bold mb-1">Gestion FTP</h2>
                <div class="text-muted">Administration / Gerer FTP</div>
            </div>
            <span class="badge text-bg-light border align-self-start align-self-lg-center">
                Maquette interface - aucune connexion reelle
            </span>
        </div>

        <section class="row g-3 mb-3">
            <div class="col-12 col-xl-7">
                <div class="soft-card p-3 p-lg-4 h-100">
                    <h3 class="ftp-card-title mb-3">
                        <i class="bi bi-gear-fill me-2"></i>
                        Configuration FTP
                    </h3>

                    <div class="row g-3 align-items-center">
                        <div class="col-12 col-lg-3">
                            <label class="ftp-form-label">Protocole :</label>
                        </div>
                        <div class="col-12 col-lg-9">
                            <select class="form-select ftp-muted-control" disabled>
                                <option>FTP - protocole de transfert de fichiers</option>
                            </select>
                        </div>

                        <div class="col-12 col-lg-3">
                            <label class="ftp-form-label">Hote :</label>
                        </div>
                        <div class="col-12 col-lg-6">
                            <input class="form-control ftp-muted-control" value="10.57.63.109" disabled>
                        </div>
                        <div class="col-4 col-lg-1">
                            <label class="ftp-form-label">Port :</label>
                        </div>
                        <div class="col-8 col-lg-2">
                            <input class="form-control ftp-muted-control" value="21" disabled>
                        </div>

                        <div class="col-12 col-lg-3">
                            <label class="ftp-form-label">Chiffrement :</label>
                        </div>
                        <div class="col-12 col-lg-9">
                            <select class="form-select ftp-muted-control" disabled>
                                <option>Connexion FTP explicite sur TLS si disponible</option>
                            </select>
                        </div>

                        <div class="col-12 col-lg-3">
                            <label class="ftp-form-label">Type d'authentification :</label>
                        </div>
                        <div class="col-12 col-lg-9">
                            <select class="form-select ftp-muted-control" disabled>
                                <option>Normale</option>
                            </select>
                        </div>

                        <div class="col-12 col-lg-3">
                            <label class="ftp-form-label">Utilisateur :</label>
                        </div>
                        <div class="col-12 col-lg-9">
                            <input class="form-control ftp-muted-control" value="RAFTP" disabled>
                        </div>

                        <div class="col-12 col-lg-3">
                            <label class="ftp-form-label">Mot de passe :</label>
                        </div>
                        <div class="col-12 col-lg-9">
                            <input type="password" class="form-control ftp-muted-control" value="password" disabled>
                        </div>

                        <div class="col-12">
                            <hr class="my-1">
                        </div>

                        <div class="col-12 col-lg-3">
                            <label class="ftp-form-label">Couleur de fond :</label>
                        </div>
                        <div class="col-12 col-lg-3">
                            <select class="form-select ftp-muted-control" disabled>
                                <option>Aucune</option>
                            </select>
                        </div>

                        <div class="col-12 col-lg-3">
                            <label class="ftp-form-label">Commentaires :</label>
                        </div>
                        <div class="col-12 col-lg-9">
                            <textarea class="form-control ftp-muted-control" rows="2" disabled>Entrez un commentaire optionnel...</textarea>
                        </div>

                        <div class="col-12 d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-primary" disabled>
                                <i class="bi bi-save me-1"></i>
                                Enregistrer
                            </button>
                            <button type="button" class="btn btn-light border" disabled>
                                <i class="bi bi-wifi me-1"></i>
                                Test de connexion
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-5">
                <div class="soft-card p-3 p-lg-4 h-100">
                    <h3 class="ftp-card-title mb-3">
                        <i class="bi bi-hdd-rack-fill me-2"></i>
                        Test de connexion
                    </h3>

                    <div class="ftp-status-box d-flex gap-3 mb-3">
                        <span class="ftp-status-icon">
                            <i class="bi bi-check-lg"></i>
                        </span>
                        <div>
                            <div class="fw-bold text-success">Apercu pret</div>
                            <div class="small text-muted">La logique de connexion sera branchee lorsque les identifiants FTP seront disponibles.</div>
                        </div>
                    </div>

                    <dl class="row mb-3">
                        <dt class="col-5">Statut :</dt>
                        <dd class="col-7"><span class="badge text-bg-secondary">Non teste</span></dd>
                        <dt class="col-5">Hote :</dt>
                        <dd class="col-7">10.57.63.109</dd>
                        <dt class="col-5">Port :</dt>
                        <dd class="col-7">21</dd>
                        <dt class="col-5">Protocole :</dt>
                        <dd class="col-7">FTP</dd>
                        <dt class="col-5">Chiffrement :</dt>
                        <dd class="col-7">TLS disponible</dd>
                        <dt class="col-5">Utilisateur :</dt>
                        <dd class="col-7">RAFTP</dd>
                        <dt class="col-5">Dernier test :</dt>
                        <dd class="col-7">--/--/---- --:--:--</dd>
                    </dl>

                    <button type="button" class="btn btn-primary" disabled>
                        <i class="bi bi-arrow-repeat me-1"></i>
                        Tester a nouveau
                    </button>
                </div>
            </div>
        </section>

        <section class="soft-card p-3 p-lg-4 mb-3">
            <div class="ftp-toolbar mb-3">
                <div>
                    <h3 class="ftp-card-title mb-0">Liste des fichiers CDR sur le serveur FTP</h3>
                </div>
                <div class="ftp-toolbar-actions">
                    <button type="button" class="btn btn-primary" disabled>
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Actualiser
                    </button>
                    <button type="button" class="btn btn-light border" disabled>
                        <i class="bi bi-download me-1"></i>
                        Telecharger
                    </button>
                    <button type="button" class="btn btn-outline-danger" disabled>
                        <i class="bi bi-trash3 me-1"></i>
                        Supprimer
                    </button>
                </div>
                <div class="input-group ftp-search">
                    <input class="form-control" placeholder="Rechercher un fichier..." disabled>
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th><input type="checkbox" disabled></th>
                            <th></th>
                            <th>Nom du fichier</th>
                            <th>Taille</th>
                            <th>Date de modification</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="checkbox" disabled></td>
                            <td><i class="bi bi-file-earmark-text fs-5 text-secondary"></i></td>
                            <td class="fw-semibold">MMG_CDR_20250521_0001.dat</td>
                            <td>152.45 MB</td>
                            <td>21/05/2025 08:00:00</td>
                            <td>MMG</td>
                            <td><span class="badge text-bg-primary">Nouveau</span></td>
                            <td class="text-end ftp-table-actions">
                                <button class="btn btn-sm btn-success" disabled><i class="bi bi-download"></i></button>
                                <button class="btn btn-sm btn-warning text-white" disabled><i class="bi bi-list-ul"></i></button>
                                <button class="btn btn-sm btn-danger" disabled><i class="bi bi-trash3"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td><input type="checkbox" disabled></td>
                            <td><i class="bi bi-file-earmark-text fs-5 text-secondary"></i></td>
                            <td class="fw-semibold">MMG_CDR_20250520_0001.dat</td>
                            <td>148.20 MB</td>
                            <td>20/05/2025 08:00:00</td>
                            <td>MMG</td>
                            <td><span class="badge text-bg-success">Importe</span></td>
                            <td class="text-end ftp-table-actions">
                                <button class="btn btn-sm btn-success" disabled><i class="bi bi-download"></i></button>
                                <button class="btn btn-sm btn-warning text-white" disabled><i class="bi bi-list-ul"></i></button>
                                <button class="btn btn-sm btn-danger" disabled><i class="bi bi-trash3"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td><input type="checkbox" disabled></td>
                            <td><i class="bi bi-file-earmark-text fs-5 text-secondary"></i></td>
                            <td class="fw-semibold">OCC_CDR_20250521_0001.dat</td>
                            <td>98.12 MB</td>
                            <td>21/05/2025 08:05:00</td>
                            <td>OCC</td>
                            <td><span class="badge text-bg-primary">Nouveau</span></td>
                            <td class="text-end ftp-table-actions">
                                <button class="btn btn-sm btn-success" disabled><i class="bi bi-download"></i></button>
                                <button class="btn btn-sm btn-warning text-white" disabled><i class="bi bi-list-ul"></i></button>
                                <button class="btn btn-sm btn-danger" disabled><i class="bi bi-trash3"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td><input type="checkbox" disabled></td>
                            <td><i class="bi bi-file-earmark-text fs-5 text-secondary"></i></td>
                            <td class="fw-semibold">OCC_CDR_20250520_0001.dat</td>
                            <td>96.35 MB</td>
                            <td>20/05/2025 08:05:00</td>
                            <td>OCC</td>
                            <td><span class="badge text-bg-success">Importe</span></td>
                            <td class="text-end ftp-table-actions">
                                <button class="btn btn-sm btn-success" disabled><i class="bi bi-download"></i></button>
                                <button class="btn btn-sm btn-warning text-white" disabled><i class="bi bi-list-ul"></i></button>
                                <button class="btn btn-sm btn-danger" disabled><i class="bi bi-trash3"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td><input type="checkbox" disabled></td>
                            <td><i class="bi bi-file-earmark-text fs-5 text-secondary"></i></td>
                            <td class="fw-semibold">MMG_CDR_20250519_0001.dat</td>
                            <td>151.11 MB</td>
                            <td>19/05/2025 08:00:00</td>
                            <td>MMG</td>
                            <td><span class="badge text-bg-success">Importe</span></td>
                            <td class="text-end ftp-table-actions">
                                <button class="btn btn-sm btn-success" disabled><i class="bi bi-download"></i></button>
                                <button class="btn btn-sm btn-warning text-white" disabled><i class="bi bi-list-ul"></i></button>
                                <button class="btn btn-sm btn-danger" disabled><i class="bi bi-trash3"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="soft-card p-3 p-lg-4">
            <h3 class="ftp-card-title mb-3">Historique des imports</h3>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fichier</th>
                            <th>Type</th>
                            <th>Date d'import</th>
                            <th>Lignes</th>
                            <th>Statut</th>
                            <th>Message</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>MMG_CDR_20250520_0001.dat</td>
                            <td>MMG</td>
                            <td>20/05/2025 08:15:22</td>
                            <td>1 245 678</td>
                            <td><span class="badge text-bg-success">Succes</span></td>
                            <td>Import termine avec succes.</td>
                            <td class="text-end"><button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-file-earmark-text"></i></button></td>
                        </tr>
                        <tr>
                            <td>OCC_CDR_20250520_0001.dat</td>
                            <td>OCC</td>
                            <td>20/05/2025 08:18:47</td>
                            <td>987 654</td>
                            <td><span class="badge text-bg-success">Succes</span></td>
                            <td>Import termine avec succes.</td>
                            <td class="text-end"><button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-file-earmark-text"></i></button></td>
                        </tr>
                        <tr>
                            <td>MMG_CDR_20250519_0001.dat</td>
                            <td>MMG</td>
                            <td>19/05/2025 08:14:11</td>
                            <td>1 198 765</td>
                            <td><span class="badge text-bg-danger">Erreur</span></td>
                            <td>Ligne 12560 : format invalide.</td>
                            <td class="text-end"><button class="btn btn-sm btn-outline-secondary" disabled><i class="bi bi-file-earmark-text"></i></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
