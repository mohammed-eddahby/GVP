"""
Configuration centralisée du module Analytics GVP.
Les valeurs par défaut suivent la configuration XAMPP locale.
Aucune donnée n'est mise en cache : uniquement chemins et paramètres.
"""
import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
PROJECT_ROOT = BASE_DIR.parent
OUTPUT_DIR = PROJECT_ROOT / "assets" / "analytics"
JSON_OUTPUT_PATH = OUTPUT_DIR / "analytics_data.json"

DB_HOST = os.getenv("GVP_DB_HOST", "localhost")
DB_PORT = int(os.getenv("GVP_DB_PORT", "3306"))
DB_NAME = os.getenv("GVP_DB_NAME", "gvp_db")
DB_USER = os.getenv("GVP_DB_USER", "root")
DB_PASSWORD = os.getenv("GVP_DB_PASSWORD", "")

SQLALCHEMY_DATABASE_URL = (
    f"mysql+pymysql://{DB_USER}:{DB_PASSWORD}@{DB_HOST}:{DB_PORT}/{DB_NAME}?charset=utf8mb4"
)

TOP_CLIENTS_LIMIT = int(os.getenv("GVP_TOP_CLIENTS_LIMIT", "5"))