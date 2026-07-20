"""
Calcul des KPI métier à partir des DataFrames nettoyés.
Le calcul est entièrement recalculé depuis MySQL à chaque exécution.
"""
from __future__ import annotations

import pandas as pd


def _pct(numerator: int, denominator: int) -> float:
    if not denominator:
        return 0.0
    return round((numerator / denominator) * 100, 2)


def taux_visites_realisees(visites: pd.DataFrame) -> dict:
    total = int(len(visites))
    realisees = int(visites["est_realisee"].fillna(False).sum()) if "est_realisee" in visites.columns else 0
    return {
        "valeur": _pct(realisees, total),
        "unite": "%",
        "realisees": realisees,
        "total": total,
    }


def taux_rapports_valides(rapports: pd.DataFrame) -> dict:
    total = int(len(rapports))
    valides = int(rapports["statut"].eq("valide").sum()) if "statut" in rapports.columns else 0
    return {
        "valeur": _pct(valides, total),
        "unite": "%",
        "valides": valides,
        "total": total,
    }


def total_visites(visites: pd.DataFrame) -> int:
    return int(len(visites))


def total_clients_actifs(clients: pd.DataFrame) -> int:
    return int(clients["actif"].eq(1).sum()) if "actif" in clients.columns else 0


def visites_par_technicien(visites: pd.DataFrame) -> list[dict]:
    if "technicien_nom_complet" not in visites.columns:
        return []
    grouped = (
        visites.assign(technicien_nom_complet=visites["technicien_nom_complet"].fillna("Non assigné"))
        .groupby("technicien_nom_complet", dropna=False)
        .size()
        .reset_index(name="nb_visites")
        .sort_values(["nb_visites", "technicien_nom_complet"], ascending=[False, True])
    )
    return [
        {"technicien": row["technicien_nom_complet"], "nb_visites": int(row["nb_visites"])}
        for _, row in grouped.iterrows()
    ]


def visites_par_mois(visites: pd.DataFrame) -> list[dict]:
    if "mois" not in visites.columns:
        return []
    grouped = (
        visites.dropna(subset=["mois"])
        .groupby("mois")
        .size()
        .reset_index(name="nb_visites")
        .sort_values("mois")
    )
    return [
        {"mois": row["mois"], "nb_visites": int(row["nb_visites"])}
        for _, row in grouped.iterrows()
    ]


def top_clients(visites: pd.DataFrame, limit: int = 5) -> list[dict]:
    if "nom_entreprise" not in visites.columns:
        return []
    grouped = (
        visites.groupby("nom_entreprise")
        .size()
        .reset_index(name="nb_visites")
        .sort_values(["nb_visites", "nom_entreprise"], ascending=[False, True])
        .head(limit)
    )
    return [
        {"client": row["nom_entreprise"], "nb_visites": int(row["nb_visites"])}
        for _, row in grouped.iterrows()
    ]


def compute_all_kpis(visites: pd.DataFrame, rapports: pd.DataFrame, clients: pd.DataFrame, top_clients_limit: int = 5) -> dict:
    """Recalcule l'intégralité des KPI à partir des données MySQL fraîches."""
    return {
        "kpis": {
            "taux_visites_realisees": taux_visites_realisees(visites),
            "taux_rapports_valides": taux_rapports_valides(rapports),
            "total_visites": total_visites(visites),
            "total_clients_actifs": total_clients_actifs(clients),
        },
        "visites_par_mois": visites_par_mois(visites),
        "visites_par_technicien": visites_par_technicien(visites),
        "top_clients": top_clients(visites, top_clients_limit),
    }


if __name__ == "__main__":
    from clean_data import clean_all
    from extract_data import extract_all

    cleaned = clean_all(extract_all())
    result = compute_all_kpis(cleaned["visites"], cleaned["rapports"], cleaned["clients"])
    import json
    print(json.dumps(result, indent=2, ensure_ascii=False))