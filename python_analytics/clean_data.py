"""
Nettoyage et normalisation des DataFrames extraits de MySQL.
L’objectif est de produire une base homogène pour le calcul des KPI sans dépendre de données historiques ou de cache.
"""
from __future__ import annotations

import pandas as pd


def clean_visites(df: pd.DataFrame) -> pd.DataFrame:
    df = df.copy()

    for column in ["date_prevue", "date_realisee"]:
        if column in df.columns:
            df[column] = pd.to_datetime(df[column], errors="coerce")
        else:
            df[column] = pd.NaT

    if {"tech_prenom", "tech_nom"}.issubset(set(df.columns)):
        df["technicien_nom_complet"] = (
            df["tech_prenom"].fillna("").astype(str).str.strip()
            + " " 
            + df["tech_nom"].fillna("").astype(str).str.strip()
        ).str.strip()
        df["technicien_nom_complet"] = df["technicien_nom_complet"].replace(r"^\s*$", "Non assigné", regex=True)
    else:
        df["technicien_nom_complet"] = "Non assigné"

    if "nom_entreprise" in df.columns:
        df["nom_entreprise"] = df["nom_entreprise"].fillna("Client inconnu")
    else:
        df["nom_entreprise"] = "Client inconnu"

    if "date_prevue" in df.columns:
        df["mois"] = df["date_prevue"].dt.to_period("M").astype(str)
        df.loc[df["date_prevue"].isna(), "mois"] = None
    else:
        df["mois"] = None

    if "statut" in df.columns:
        df["statut"] = df["statut"].fillna("inconnu")
    else:
        df["statut"] = "inconnu"

    df["est_realisee"] = False
    if "statut" in df.columns:
        df["est_realisee"] = df["est_realisee"] | df["statut"].eq("realisee")
    if "date_realisee" in df.columns:
        df["est_realisee"] = df["est_realisee"] | df["date_realisee"].notna()

    return df


def clean_rapports(df: pd.DataFrame) -> pd.DataFrame:
    df = df.copy()
    for column in ["date_soumission", "date_validation", "created_at"]:
        if column in df.columns:
            df[column] = pd.to_datetime(df[column], errors="coerce")
        else:
            df[column] = pd.NaT
    if "statut" in df.columns:
        df["statut"] = df["statut"].fillna("inconnu")
    else:
        df["statut"] = "inconnu"
    return df


def clean_clients(df: pd.DataFrame) -> pd.DataFrame:
    df = df.copy()
    if "ville" in df.columns:
        df["ville"] = df["ville"].fillna("Non renseignée")
    else:
        df["ville"] = "Non renseignée"
    if "actif" in df.columns:
        df["actif"] = df["actif"].fillna(0).astype(int)
    else:
        df["actif"] = 0
    return df


def clean_all(raw: dict[str, pd.DataFrame]) -> dict[str, pd.DataFrame]:
    return {
        "visites": clean_visites(raw["visites"]),
        "rapports": clean_rapports(raw["rapports"]),
        "clients": clean_clients(raw["clients"]),
        "utilisateurs": raw["utilisateurs"],
    }


if __name__ == "__main__":
    from extract_data import extract_all

    cleaned = clean_all(extract_all())
    for name, df in cleaned.items():
        print(f"{name}: {len(df)} lignes nettoyées")