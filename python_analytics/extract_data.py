"""
Extraction fraîche des données depuis MySQL.
Les requêtes sont adaptées à la structure réelle des tables présentes dans la base.
"""
from __future__ import annotations

import pandas as pd
from sqlalchemy import create_engine, text
from sqlalchemy.engine import Engine

from config import SQLALCHEMY_DATABASE_URL


def get_engine() -> Engine:
    """Crée un moteur SQLAlchemy connecté à la base courante."""
    return create_engine(SQLALCHEMY_DATABASE_URL, pool_pre_ping=True, pool_recycle=280)


def _table_columns(engine: Engine, table_name: str) -> set[str]:
    with engine.connect() as conn:
        result = conn.execute(text(f"DESCRIBE `{table_name}`"))
        return {row[0] for row in result.fetchall()}


def _select_existing_columns(table_name: str, engine: Engine, preferred_columns: list[str]) -> list[str]:
    available = _table_columns(engine, table_name)
    selected = [column for column in preferred_columns if column in available]
    return selected or ["*"]


def extract_visites(engine: Engine) -> pd.DataFrame:
    """Extrait toutes les visites avec les joins SQL nécessaires si les colonnes existent."""
    visit_columns = _select_existing_columns("visites", engine, [
        "id", "site_id", "technicien_id", "type_visite", "date_prevue", "date_realisee", "statut"
    ])
    site_columns = _select_existing_columns("sites", engine, ["id", "client_id", "nom_site"])
    client_columns = _select_existing_columns("clients", engine, ["id", "nom_entreprise", "ville"])
    user_columns = _select_existing_columns("utilisateurs", engine, ["id", "nom", "prenom"])

    select_fields = [f"v.{column}" if column in {"id", "site_id", "technicien_id", "type_visite", "date_prevue", "date_realisee", "statut"} else column for column in visit_columns]
    if "nom_site" in site_columns:
        select_fields.append("s.nom_site")
    if "nom_entreprise" in client_columns:
        select_fields.append("c.nom_entreprise")
    if "ville" in client_columns:
        select_fields.append("c.ville AS client_ville")
    if "nom" in user_columns:
        select_fields.append("u.nom AS tech_nom")
    if "prenom" in user_columns:
        select_fields.append("u.prenom AS tech_prenom")

    query = f"SELECT {', '.join(select_fields)} FROM visites v LEFT JOIN sites s ON s.id = v.site_id LEFT JOIN clients c ON c.id = s.client_id LEFT JOIN utilisateurs u ON u.id = v.technicien_id"
    return pd.read_sql(query, engine)


def extract_rapports(engine: Engine) -> pd.DataFrame:
    """Extrait tous les rapports, avec les colonnes disponibles dans la table."""
    columns = _select_existing_columns("rapports", engine, [
        "id", "visite_id", "redige_par", "valide_par", "statut", "date_soumission", "date_validation", "created_at"
    ])
    query = f"SELECT {', '.join(columns)} FROM rapports r"
    return pd.read_sql(query, engine)


def extract_clients(engine: Engine) -> pd.DataFrame:
    """Extrait les clients et leurs colonnes réelles actuellement présentes."""
    columns = _select_existing_columns("clients", engine, ["id", "nom_entreprise", "ville", "actif"])
    query = f"SELECT {', '.join(columns)} FROM clients"
    return pd.read_sql(query, engine)


def extract_utilisateurs(engine: Engine) -> pd.DataFrame:
    """Extrait les utilisateurs tels qu'ils existent réellement dans la base."""
    columns = _select_existing_columns("utilisateurs", engine, ["id", "nom", "prenom", "role", "actif"])
    query = f"SELECT {', '.join(columns)} FROM utilisateurs"
    return pd.read_sql(query, engine)


def extract_all() -> dict[str, pd.DataFrame]:
    """Point d'entrée unique : ouvre une connexion, exécute toutes les requêtes et ferme proprement la connexion."""
    engine = get_engine()
    try:
        data = {
            "visites": extract_visites(engine),
            "rapports": extract_rapports(engine),
            "clients": extract_clients(engine),
            "utilisateurs": extract_utilisateurs(engine),
        }
    finally:
        engine.dispose()
    return data


if __name__ == "__main__":
    data = extract_all()
    for name, df in data.items():
        print(f"{name}: {len(df)} lignes")