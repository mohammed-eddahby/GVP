"""
Génération des graphiques Plotly HTML et Matplotlib PNG à partir des KPI recalculés.
Les sorties sont toujours écrasées pour éviter toute incohérence due à des données anciennes.
"""
from __future__ import annotations

import os
from pathlib import Path

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import plotly.express as px
import plotly.io as pio

from config import OUTPUT_DIR

COLOR_PRIMARY = "#4f7cff"
COLOR_MUTED = "#e5e7eb"


def _ensure_output_dir() -> Path:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    return OUTPUT_DIR


def chart_visites_par_mois_html(rows: list[dict], filename: str = "visites_par_mois.html") -> str:
    output_dir = _ensure_output_dir()
    mois = [row["mois"] for row in rows]
    nb = [row["nb_visites"] for row in rows]
    fig = px.bar(
        x=mois,
        y=nb,
        title="Visites par mois",
        labels={"x": "Mois", "y": "Nombre de visites"},
    )
    fig.update_traces(marker_color=COLOR_PRIMARY)
    fig.update_layout(template="plotly_dark", margin=dict(l=20, r=20, t=50, b=20))
    path = output_dir / filename
    pio.write_html(fig, file=str(path), full_html=True, include_plotlyjs="cdn")
    return str(path)


def chart_visites_par_technicien_html(rows: list[dict], filename: str = "visites_par_technicien.html") -> str:
    output_dir = _ensure_output_dir()
    techniciens = [row["technicien"] for row in rows]
    nb = [row["nb_visites"] for row in rows]
    fig = px.bar(
        x=techniciens,
        y=nb,
        title="Visites par technicien",
        labels={"x": "Technicien", "y": "Nombre de visites"},
    )
    fig.update_traces(marker_color=COLOR_PRIMARY)
    fig.update_layout(template="plotly_dark", margin=dict(l=20, r=20, t=50, b=20))
    path = output_dir / filename
    pio.write_html(fig, file=str(path), full_html=True, include_plotlyjs="cdn")
    return str(path)


def chart_top_clients_html(rows: list[dict], filename: str = "top_clients.html") -> str:
    output_dir = _ensure_output_dir()
    clients = [row["client"] for row in rows]
    nb = [row["nb_visites"] for row in rows]
    fig = px.pie(names=clients, values=nb, title="Top clients")
    fig.update_layout(template="plotly_dark", margin=dict(l=20, r=20, t=50, b=20))
    path = output_dir / filename
    pio.write_html(fig, file=str(path), full_html=True, include_plotlyjs="cdn")
    return str(path)


def chart_taux_realisation_png(taux: float, filename: str = "taux_realisation.png") -> str:
    output_dir = _ensure_output_dir()
    taux = max(0.0, min(100.0, float(taux)))
    fig, ax = plt.subplots(figsize=(4, 4))
    ax.pie([taux, 100 - taux], labels=["Réalisées", "Restantes"], autopct="%1.1f%%", colors=[COLOR_PRIMARY, COLOR_MUTED])
    ax.set_title("Taux de visites réalisées")
    path = output_dir / filename
    fig.savefig(path, bbox_inches="tight", dpi=150)
    plt.close(fig)
    return str(path)


def chart_visites_par_mois_png(rows: list[dict], filename: str = "visites_par_mois.png") -> str:
    output_dir = _ensure_output_dir()
    mois = [row["mois"] for row in rows]
    nb = [row["nb_visites"] for row in rows]
    fig, ax = plt.subplots(figsize=(6, 4))
    ax.bar(mois, nb, color=COLOR_PRIMARY)
    ax.set_title("Visites par mois")
    ax.set_xlabel("Mois")
    ax.set_ylabel("Nombre de visites")
    plt.xticks(rotation=45, ha="right")
    path = output_dir / filename
    fig.savefig(path, bbox_inches="tight", dpi=150)
    plt.close(fig)
    return str(path)


def generate_all_charts(kpi_result: dict) -> dict:
    """Régénère tous les graphiques d’analytics et renvoie les chemins relatifs utilisés par le dashboard PHP."""
    visites_par_mois_rows = kpi_result.get("visites_par_mois", [])
    visites_par_technicien_rows = kpi_result.get("visites_par_technicien", [])
    top_clients_rows = kpi_result.get("top_clients", [])
    taux_realisation = kpi_result["kpis"]["taux_visites_realisees"]["valeur"]

    chart_visites_par_mois_html(visites_par_mois_rows)
    chart_visites_par_technicien_html(visites_par_technicien_rows)
    chart_top_clients_html(top_clients_rows)
    chart_taux_realisation_png(taux_realisation)
    chart_visites_par_mois_png(visites_par_mois_rows)

    return {
        "visites_par_mois_html": "assets/analytics/visites_par_mois.html",
        "visites_par_technicien_html": "assets/analytics/visites_par_technicien.html",
        "top_clients_html": "assets/analytics/top_clients.html",
        "taux_realisation_png": "assets/analytics/taux_realisation.png",
        "visites_par_mois_png": "assets/analytics/visites_par_mois.png",
    }