"""
Script principal du module Analytics GVP.
Réalise une chaîne complète : extraction MySQL -> nettoyage -> recalcul KPI -> génération graphiques -> écriture du JSON.
"""
from __future__ import annotations

import json
import sys
from datetime import datetime
from pathlib import Path

from sqlalchemy import text

from clean_data import clean_all
from config import JSON_OUTPUT_PATH, TOP_CLIENTS_LIMIT
from extract_data import extract_all, get_engine
from kpi_calculator import compute_all_kpis
from visualizations import generate_all_charts


def refresh_analytics_kpi_table(engine, kpi_result: dict) -> None:
    """Rafraîchit la table analytics_kpi en remplacement de son contenu précédent (best effort)."""
    try:
        with engine.begin() as conn:
            conn.execute(text("DELETE FROM analytics_kpi"))
            rows_to_insert = []
            k = kpi_result["kpis"]
            rows_to_insert.append(("taux_visites_realisees", k["taux_visites_realisees"]["valeur"], "%", "global", k["taux_visites_realisees"]))
            rows_to_insert.append(("taux_rapports_valides", k["taux_rapports_valides"]["valeur"], "%", "global", k["taux_rapports_valides"]))
            rows_to_insert.append(("total_visites", k["total_visites"], "visites", "global", {}))
            rows_to_insert.append(("total_clients_actifs", k["total_clients_actifs"], "clients", "global", {}))
            for row in kpi_result.get("visites_par_technicien", []):
                rows_to_insert.append(("visites_par_technicien", row["nb_visites"], "visites", row["technicien"], {"technicien": row["technicien"]}))
            for row in kpi_result.get("visites_par_mois", []):
                rows_to_insert.append(("visites_par_mois", row["nb_visites"], "visites", row["mois"], {"mois": row["mois"]}))
            for row in kpi_result.get("top_clients", []):
                rows_to_insert.append(("top_client", row["nb_visites"], "visites", row["client"], {"client": row["client"]}))

            now = datetime.now()
            for nom, valeur, unite, periode, metadata in rows_to_insert:
                conn.execute(
                    text(
                        """INSERT INTO analytics_kpi (nom_kpi, valeur, unite, periode, metadata, date_calcul)
                           VALUES (:nom, :valeur, :unite, :periode, :metadata, :dc)"""
                    ),
                    {
                        "nom": nom,
                        "valeur": float(valeur),
                        "unite": unite,
                        "periode": periode,
                        "metadata": json.dumps(metadata, ensure_ascii=False),
                        "dc": now,
                    },
                )
    except Exception as exc:  # non bloquant : le JSON reste la source de vérité
        print(f"   (avertissement) Impossible de rafraîchir analytics_kpi : {exc}")


def main() -> None:
    print("== GVP Analytics : démarrage ==")
    engine = get_engine()
    try:
        print("1/5 Extraction des données depuis MySQL (temps réel)...")
        raw_data = extract_all()

        print("2/5 Nettoyage des données extraites...")
        cleaned = clean_all(raw_data)
        visites = cleaned["visites"]
        rapports = cleaned["rapports"]
        clients = cleaned["clients"]

        if visites.empty:
            print("Aucune visite n'a été trouvée dans la base. Calcul des KPI impossible.")
            sys.exit(1)

        print("3/5 Recalcul complet des KPI depuis les données actuelles...")
        kpi_result = compute_all_kpis(visites, rapports, clients, TOP_CLIENTS_LIMIT)

        print("4/5 Génération des graphiques Plotly HTML + Matplotlib PNG...")
        chart_paths = generate_all_charts(kpi_result)
        for name, path in chart_paths.items():
            print(f"   -> {name}: {path}")

        print("5/5 Écriture du fichier JSON unique pour le dashboard PHP...")
        payload = {
            "generated_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "kpis": kpi_result["kpis"],
            "visites_par_mois": kpi_result["visites_par_mois"],
            "visites_par_technicien": kpi_result["visites_par_technicien"],
            "top_clients": kpi_result["top_clients"],
            "charts": chart_paths,
        }
        output_path = Path(JSON_OUTPUT_PATH)
        output_path.parent.mkdir(parents=True, exist_ok=True)
        with output_path.open("w", encoding="utf-8") as handle:
            json.dump(payload, handle, ensure_ascii=False, indent=2)
        print(f"   -> {output_path}")

        print("Synchronisation best-effort de la table analytics_kpi...")
        refresh_analytics_kpi_table(engine, kpi_result)

        print("== GVP Analytics : terminé avec succès ==")
    finally:
        engine.dispose()


if __name__ == "__main__":
    main()