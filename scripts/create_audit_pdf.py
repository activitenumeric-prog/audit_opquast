#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
create_audit_pdf.py — Générateur de rapport PDF Opquast
Usage: python3 create_audit_pdf.py <chemin_csv> <chemin_pdf_sortie>
"""

import sys
import os
import csv
import json
from datetime import datetime

from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.lib.units import mm
from reportlab.lib.styles import ParagraphStyle
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle,
    PageBreak, HRFlowable, KeepTogether
)
from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_RIGHT

# ─── Chemins par défaut ────────────────────────────────────────────────────────
DEFAULT_CSV = "audit_opquast.csv"
DEFAULT_PDF = "rapport_audit_opquast.pdf"
LANG_FILE   = os.path.join(os.path.dirname(__file__), "lang_fr.json")

# ─── Palette de couleurs ───────────────────────────────────────────────────────
NAVY        = colors.HexColor("#1A252F")
DARK_BLUE   = colors.HexColor("#2C3E50")
ACCENT      = colors.HexColor("#3498DB")
TEAL        = colors.HexColor("#1ABC9C")
GREEN       = colors.HexColor("#2ECC71")
RED         = colors.HexColor("#E74C3C")
ORANGE      = colors.HexColor("#F39C12")
GREY        = colors.HexColor("#95A5A6")
LIGHT_BG    = colors.HexColor("#F8F9FA")
ALT_ROW     = colors.HexColor("#EBF5FB")
WHITE       = colors.white
BLACK       = colors.black

# Couleurs pâles pour fiches
RED_PALE    = colors.HexColor("#FDEDEC")
GREEN_PALE  = colors.HexColor("#EAFAF1")
BLUE_PALE   = colors.HexColor("#EBF5FB")
GREEN_DARK  = colors.HexColor("#1E8449")
RED_DARK    = colors.HexColor("#C0392B")

# Matplotlib hex strings
M_NAVY      = "#1A252F"
M_ACCENT    = "#3498DB"
M_TEAL      = "#1ABC9C"
M_GREEN     = "#2ECC71"
M_RED       = "#E74C3C"
M_ORANGE    = "#F39C12"
M_GREY      = "#95A5A6"
M_DARKBLUE  = "#2C3E50"

# ─── Dimensions A4 ────────────────────────────────────────────────────────────
PAGE_W, PAGE_H = A4          # 595.28 x 841.89 pts
MARGIN      = 20 * mm
CONTENT_W   = PAGE_W - 2 * MARGIN
HEADER_H    = 18 * mm
FOOTER_H    = 12 * mm

# ─── Familles Opquast ─────────────────────────────────────────────────────────
FAMILIES = [
    "Contenus", "Données personnelles", "E-Commerce", "Formulaires",
    "Identification et contact", "Images et médias", "Internationalisation",
    "Liens", "Navigation", "Newsletter", "Présentation",
    "Serveur et performances", "Structure et code", "Sécurité"
]

STATUTS = ["Conforme", "Non conforme", "A verifier", "Non applicable"]
STATUT_COLORS = {
    "Conforme":       GREEN,
    "Non conforme":   RED,
    "A verifier":     ORANGE,
    "Non applicable": GREY,
}
STATUT_COLORS_M = {
    "Conforme":       M_GREEN,
    "Non conforme":   M_RED,
    "A verifier":     M_ORANGE,
    "Non applicable": M_GREY,
}

# ─── Chargement langue ────────────────────────────────────────────────────────
def load_lang():
    try:
        with open(LANG_FILE, encoding="utf-8") as f:
            return json.load(f)
    except Exception:
        return {}

LANG = load_lang()

def t(path, **kw):
    """Accès aux traductions avec chemin pointé. Ex: t('cover.tag')"""
    parts = path.split(".")
    val = LANG
    for p in parts:
        if isinstance(val, dict):
            val = val.get(p, path)
        else:
            return path
    if isinstance(val, str) and kw:
        for k, v in kw.items():
            val = val.replace("{" + k + "}", str(v))
    return val

# ─── Styles ReportLab ─────────────────────────────────────────────────────────
def make_styles():
    return {
        "normal": ParagraphStyle("normal", fontName="Helvetica", fontSize=9,
                                 textColor=DARK_BLUE, leading=13),
        "normal_white": ParagraphStyle("normal_white", fontName="Helvetica",
                                       fontSize=9, textColor=WHITE, leading=13),
        "bold": ParagraphStyle("bold", fontName="Helvetica-Bold", fontSize=9,
                               textColor=DARK_BLUE, leading=13),
        "bold_white": ParagraphStyle("bold_white", fontName="Helvetica-Bold",
                                     fontSize=9, textColor=WHITE, leading=13),
        "h1": ParagraphStyle("h1", fontName="Helvetica-Bold", fontSize=14,
                             textColor=NAVY, leading=18, spaceAfter=6),
        "h2": ParagraphStyle("h2", fontName="Helvetica-Bold", fontSize=11,
                             textColor=DARK_BLUE, leading=15, spaceAfter=4),
        "small": ParagraphStyle("small", fontName="Helvetica", fontSize=7,
                                textColor=GREY, leading=10),
        "small_white": ParagraphStyle("small_white", fontName="Helvetica",
                                      fontSize=7, textColor=WHITE, leading=10),
        "center": ParagraphStyle("center", fontName="Helvetica", fontSize=9,
                                 textColor=DARK_BLUE, leading=13, alignment=TA_CENTER),
        "center_bold": ParagraphStyle("center_bold", fontName="Helvetica-Bold",
                                      fontSize=9, textColor=DARK_BLUE, leading=13,
                                      alignment=TA_CENTER),
        "formula": ParagraphStyle("formula", fontName="Helvetica-Bold", fontSize=10,
                                  textColor=WHITE, leading=14, alignment=TA_CENTER),
        "red_bold": ParagraphStyle("red_bold", fontName="Helvetica-Bold", fontSize=9,
                                   textColor=RED_DARK, leading=13),
        "green_bold": ParagraphStyle("green_bold", fontName="Helvetica-Bold", fontSize=9,
                                     textColor=GREEN_DARK, leading=13),
        "accent_bold": ParagraphStyle("accent_bold", fontName="Helvetica-Bold", fontSize=9,
                                      textColor=ACCENT, leading=13),
        "rule_title": ParagraphStyle("rule_title", fontName="Helvetica-Bold", fontSize=9,
                                     textColor=WHITE, leading=13),
        "intitule": ParagraphStyle("intitule", fontName="Helvetica-Bold", fontSize=9,
                                   textColor=DARK_BLUE, leading=13),
    }

S = make_styles()

# ─── Lecture CSV ──────────────────────────────────────────────────────────────
def load_csv(path):
    rows = []
    encodings = ["utf-8-sig", "utf-8", "latin-1"]
    for enc in encodings:
        try:
            with open(path, encoding=enc, newline='') as f:
                reader = csv.DictReader(f, delimiter=';')
                for row in reader:
                    clean = {k.strip(): v.strip() for k, v in row.items() if k}
                    rows.append(clean)
            break
        except Exception:
            continue
    return rows

def parse_data(rows):
    """Normalise et enrichit les données du CSV."""
    data = []
    for row in rows:
        statut_raw = row.get("Statut", "").strip()
        # Normalisation "A vérifier" → "A verifier"
        if statut_raw in ("A vérifier", "À vérifier", "A verifier"):
            statut_raw = "A verifier"
        data.append({
            "audit":      row.get("Audit", ""),
            "url":        row.get("URL cible", ""),
            "type":       row.get("Type de cible", ""),
            "statut_audit": row.get("Statut audit", ""),
            "num":        row.get("Numero regle", "").strip(),
            "intitule":   row.get("Intitule", ""),
            "famille":    row.get("Famille", ""),
            "statut":     statut_raw,
            "commentaire": row.get("Commentaire", ""),
            "preuve":     row.get("Preuve ou note", ""),
            "source":     row.get("Source", ""),
        })
    return data

def compute_stats(data):
    counts = {s: 0 for s in STATUTS}
    for row in data:
        s = row["statut"]
        if s in counts:
            counts[s] += 1
    total = sum(counts.values())
    total_conformite = counts["Conforme"] + counts["Non conforme"]
    score = round(counts["Conforme"] / total_conformite * 100, 2) if total_conformite > 0 else None
    return counts, total, score

def compute_family_stats(data):
    fam_data = {f: {s: 0 for s in STATUTS} for f in FAMILIES}
    for row in data:
        fam = row["famille"]
        if fam in fam_data and row["statut"] in STATUTS:
            fam_data[fam][row["statut"]] += 1
    results = {}
    for fam, counts in fam_data.items():
        total = sum(counts.values())
        total_conformite = counts["Conforme"] + counts["Non conforme"]
        score = round(counts["Conforme"] / total_conformite * 100, 2) if total_conformite > 0 else None
        results[fam] = {**counts, "total": total, "score": score}
    return results

def group_rows_by_url(data):
    grouped = {}
    order = []

    for row in data:
        url = row["url"]
        if url not in grouped:
            grouped[url] = []
            order.append(url)
        grouped[url].append(row)

    return [(url, grouped[url]) for url in order]

def compute_site_context(data):
    urls = []
    for index, (url, rows) in enumerate(group_rows_by_url(data), start=1):
        counts, total, score = compute_stats(rows)
        family_stats = compute_family_stats(rows)
        urls.append({
            "index": index,
            "url": url,
            "label": shorten_url(url),
            "rows": rows,
            "counts": counts,
            "total": total,
            "score": score,
            "family_stats": family_stats,
        })

    counts, total, score = compute_stats(data)
    family_stats = compute_family_stats(data)

    return {
        "counts": counts,
        "total": total,
        "score": score,
        "family_stats": family_stats,
        "urls": urls,
    }

def score_color(score):
    if score is None:
        return GREY
    if score >= 80:
        return GREEN
    elif score >= 50:
        return ORANGE
    else:
        return RED

def score_color_m(score):
    if score is None:
        return M_GREY
    if score >= 80:
        return M_GREEN
    elif score >= 50:
        return M_ORANGE
    else:
        return M_RED

def format_score(score):
    if score is None:
        return "--"
    return f"{score:.2f}".replace('.', ',')

def format_percent(value):
    return f"{value:.1f}".replace('.', ',')

def shorten_url(url):
    if not url:
        return ""
    value = url.replace("https://", "").replace("http://", "")
    return value.rstrip("/")

def make_single_bar(pct, color, width):
    filled = max(width * (pct / 100.0), 0)
    if filled <= 0:
        bar = Table([[""]], colWidths=[width], rowHeights=[8])
        bar.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), LIGHT_BG),
            ("BOX", (0, 0), (-1, -1), 0.3, GREY),
        ]))
        return bar

    if filled >= width:
        bar = Table([[""]], colWidths=[width], rowHeights=[8])
        bar.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), color),
            ("BOX", (0, 0), (-1, -1), 0.3, GREY),
        ]))
        return bar

    bar = Table([["", ""]], colWidths=[filled, width - filled], rowHeights=[8])
    bar.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (0, 0), color),
        ("BACKGROUND", (1, 0), (1, 0), LIGHT_BG),
        ("BOX", (0, 0), (-1, -1), 0.3, GREY),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 0),
        ("TOPPADDING", (0, 0), (-1, -1), 0),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
    ]))
    return bar

def make_stacked_bar(counts_by_statut, width):
    total = sum(counts_by_statut.get(statut, 0) for statut in STATUTS)
    if total <= 0:
        bar = Table([[""]], colWidths=[width], rowHeights=[8])
        bar.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), WHITE),
            ("BOX", (0, 0), (-1, -1), 0.3, GREY),
        ]))
        return bar

    widths = []
    color_row = []
    for statut in STATUTS:
        value = counts_by_statut.get(statut, 0)
        if value <= 0:
            continue
        widths.append(width * (value / total))
        color_row.append(STATUT_COLORS[statut])

    remaining = width - sum(widths)
    if remaining > 0.5:
        widths.append(remaining)
        color_row.append(WHITE)

    bar = Table([[""] * len(widths)], colWidths=widths, rowHeights=[8])
    style = [
        ("BOX", (0, 0), (-1, -1), 0.3, GREY),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 0),
        ("TOPPADDING", (0, 0), (-1, -1), 0),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
    ]
    for idx, color in enumerate(color_row):
        style.append(("BACKGROUND", (idx, 0), (idx, 0), color))
    bar.setStyle(TableStyle(style))
    return bar

def build_status_distribution_table(counts, total):
    rows = [[
        Paragraph(f"<b>{t('section3.chart_pie_title')}</b>", S["bold"]),
        "",
        "",
    ]]
    bar_width = CONTENT_W * 0.36

    for statut in STATUTS:
        count = counts[statut]
        pct = (count / total * 100) if total > 0 else 0
        rows.append([
            Paragraph(t(f"statut_labels.{statut}"), S["normal"]),
            make_single_bar(pct, STATUT_COLORS[statut], bar_width),
            Paragraph(f"{count} ({format_percent(pct)}%)", S["normal"]),
        ])

    table = Table(rows, colWidths=[CONTENT_W * 0.22, CONTENT_W * 0.44, CONTENT_W * 0.20])
    table.setStyle(TableStyle([
        ("SPAN", (0, 0), (-1, 0)),
        ("BACKGROUND", (0, 0), (-1, 0), LIGHT_BG),
        ("BOX", (0, 0), (-1, -1), 0.3, GREY),
        ("INNERGRID", (0, 1), (-1, -1), 0.2, colors.HexColor("#E5E7EB")),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ]))
    return table

def build_family_distribution_table(family_stats):
    legend_cells = []
    for statut in STATUTS:
        patch = Table([[""]], colWidths=[5 * mm], rowHeights=[4 * mm])
        patch.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), STATUT_COLORS[statut]),
            ("BOX", (0, 0), (-1, -1), 0.2, GREY),
        ]))
        legend_cells.extend([patch, Paragraph(t(f"statut_labels.{statut}"), S["small"])])

    legend = Table([legend_cells], colWidths=[6 * mm, 33 * mm] * 4)
    legend.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("LEFTPADDING", (0, 0), (-1, -1), 2),
        ("RIGHTPADDING", (0, 0), (-1, -1), 4),
    ]))

    rows = [[
        Paragraph(f"<b>{t('section3.chart_bar_title')}</b>", S["bold"]),
        "",
        "",
    ], [
        legend,
        "",
        "",
    ]]

    bar_width = CONTENT_W * 0.34
    families_sorted = sorted(FAMILIES, key=lambda fam: family_stats[fam]["total"], reverse=True)

    for fam in families_sorted:
        stats = family_stats[fam]
        score_display = format_score(stats["score"])
        if stats["score"] is not None:
            score_display += "%"
        rows.append([
            Paragraph(fam, S["small"]),
            make_stacked_bar(stats, bar_width),
            Paragraph(
                f"{stats['total']} regles - {score_display}",
                ParagraphStyle(
                    "family_score",
                    fontName="Helvetica",
                    fontSize=7,
                    textColor=score_color(stats["score"]),
                    leading=9,
                )
            ),
        ])

    table = Table(rows, colWidths=[CONTENT_W * 0.28, CONTENT_W * 0.40, CONTENT_W * 0.18])
    table.setStyle(TableStyle([
        ("SPAN", (0, 0), (-1, 0)),
        ("SPAN", (0, 1), (-1, 1)),
        ("BACKGROUND", (0, 0), (-1, 0), LIGHT_BG),
        ("BOX", (0, 0), (-1, -1), 0.3, GREY),
        ("INNERGRID", (0, 2), (-1, -1), 0.2, colors.HexColor("#E5E7EB")),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ]))
    return table

# ─── Graphiques Matplotlib ────────────────────────────────────────────────────
def make_pie_chart(counts):
    labels = [t(f"statut_labels.{s}") for s in STATUTS]
    sizes  = [counts[s] for s in STATUTS]
    colors_m = [STATUT_COLORS_M[s] for s in STATUTS]

    # Filtrer les zéros
    filtered = [(l, sz, c, s) for l, sz, c, s in zip(labels, sizes, colors_m, STATUTS) if sz > 0]
    if not filtered:
        return None
    labels_f, sizes_f, colors_f, statuts_f = zip(*filtered)

    fig, ax = plt.subplots(figsize=(4.5, 3.5), facecolor='none')
    total_f = sum(sizes_f)
    wedges, _ = ax.pie(
        sizes_f,
        colors=colors_f,
        startangle=90,
        wedgeprops=dict(edgecolor='white', linewidth=1.5),
        pctdistance=0.75,
    )

    for i, (wedge, sz, lbl) in enumerate(zip(wedges, sizes_f, labels_f)):
        pct = sz / total_f * 100
        angle = (wedge.theta1 + wedge.theta2) / 2
        x_in = 0.60 * math.cos(math.radians(angle))
        y_in = 0.60 * math.sin(math.radians(angle))
        if pct >= 5:
            ax.text(x_in, y_in, f"{pct:.1f}%", ha='center', va='center',
                    fontsize=8, fontweight='bold', color='white')
        else:
            x_out = 1.25 * math.cos(math.radians(angle))
            y_out = 1.25 * math.sin(math.radians(angle))
            ax.annotate(
                f"{pct:.1f}%",
                xy=(0.9 * math.cos(math.radians(angle)),
                    0.9 * math.sin(math.radians(angle))),
                xytext=(x_out, y_out),
                fontsize=7, fontweight='bold',
                color=colors_f[i],
                arrowprops=dict(arrowstyle='->', color=colors_f[i], lw=1),
                ha='center', va='center'
            )

    ax.set_title(t("section3.chart_pie_title"), fontsize=10, fontweight='bold',
                 color=M_DARKBLUE, pad=10)
    patches = [mpatches.Patch(color=c, label=l) for c, l in zip(colors_f, labels_f)]
    ax.legend(handles=patches, loc='lower center', bbox_to_anchor=(0.5, -0.18),
              ncol=2, fontsize=7, frameon=False)
    plt.tight_layout()
    buf = io.BytesIO()
    fig.savefig(buf, format='png', dpi=120, bbox_inches='tight',
                facecolor='white', transparent=False)
    plt.close(fig)
    buf.seek(0)
    return buf

def make_gauge_chart(score):
    score_value = 0 if score is None else score
    fig, ax = plt.subplots(figsize=(4.0, 3.0), subplot_kw={'projection': 'polar'},
                           facecolor='none')
    theta_start = math.pi
    theta_end   = 0
    segments = [
        (0, 50,  M_RED,    t("section3.gauge_label_low")),
        (50, 80, M_ORANGE, t("section3.gauge_label_mid")),
        (80, 90, M_GREEN,  t("section3.gauge_label_high")),
        (90, 100,M_TEAL,   t("section3.gauge_label_top")),
    ]
    for lo, hi, col, lbl in segments:
        th1 = math.pi * (1 - lo / 100)
        th2 = math.pi * (1 - hi / 100)
        ax.barh(1, th1 - th2, left=th2, height=0.5,
                color=col, alpha=0.85, edgecolor='white', linewidth=0.5)

    needle_angle = math.pi * (1 - score_value / 100)
    ax.annotate('', xy=(needle_angle, 1.0), xytext=(0, 0),
                arrowprops=dict(arrowstyle='->', color=M_DARKBLUE, lw=2.5))

    ax.set_ylim(0, 1.6)
    ax.set_xlim(0, math.pi)
    ax.set_theta_zero_location('W')
    ax.set_theta_direction(-1)
    ax.axis('off')
    score_str = format_score(score)
    score_label = f"{score_str}%" if score is not None else score_str
    ax.text(math.pi / 2, 0.35, score_label, ha='center', va='center',
            fontsize=18, fontweight='bold', color=score_color_m(score))
    ax.set_title(t("section3.chart_gauge_title"), fontsize=10, fontweight='bold',
                 color=M_DARKBLUE, pad=4, y=0.95)
    plt.tight_layout()
    buf = io.BytesIO()
    fig.savefig(buf, format='png', dpi=120, bbox_inches='tight',
                facecolor='white', transparent=False)
    plt.close(fig)
    buf.seek(0)
    return buf

def make_bar_chart(family_stats):
    fams = list(family_stats.keys())
    conformes    = [family_stats[f]["Conforme"]       for f in fams]
    nonconformes = [family_stats[f]["Non conforme"]   for f in fams]
    averifier    = [family_stats[f]["A verifier"]     for f in fams]
    nonappl      = [family_stats[f]["Non applicable"] for f in fams]

    x = np.arange(len(fams))
    fig, ax = plt.subplots(figsize=(9, 5), facecolor='none')

    bar_w = 0.55
    bars = [
        ax.barh(x, conformes,    bar_w, color=M_GREEN,  label=t("statut_labels.Conforme"),       alpha=0.9),
        ax.barh(x, nonconformes, bar_w, left=conformes, color=M_RED,    label=t("statut_labels.Non conforme"), alpha=0.9),
    ]
    left2 = [a + b for a, b in zip(conformes, nonconformes)]
    bars.append(ax.barh(x, averifier, bar_w, left=left2, color=M_ORANGE,
                        label=t("statut_labels.A verifier"), alpha=0.9))
    left3 = [a + b for a, b in zip(left2, averifier)]
    bars.append(ax.barh(x, nonappl, bar_w, left=left3, color=M_GREY,
                        label=t("statut_labels.Non applicable"), alpha=0.9))

    short_names = [f[:22] + "…" if len(f) > 22 else f for f in fams]
    ax.set_yticks(x)
    ax.set_yticklabels(short_names, fontsize=7.5)
    ax.set_xlabel(t("section3.chart_bar_xlabel"), fontsize=8)
    ax.set_title(t("section3.chart_bar_title"), fontsize=11, fontweight='bold',
                 color=M_DARKBLUE, pad=10)
    ax.legend(loc='lower right', fontsize=7.5, frameon=True, framealpha=0.8)
    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    ax.tick_params(axis='y', length=0)
    plt.tight_layout()
    buf = io.BytesIO()
    fig.savefig(buf, format='png', dpi=120, bbox_inches='tight',
                facecolor='white', transparent=False)
    plt.close(fig)
    buf.seek(0)
    return buf

# ─── En-tête et pied de page ──────────────────────────────────────────────────
def make_header_footer(audit_name, audit_url, page_num, page_count):
    """Retourne (header_flowable, footer_flowable) sous forme de Tables canvas-drawn."""
    pass  # Ces éléments sont gérés via onFirstPage/onLaterPages

def on_page(canvas, doc, audit_name, audit_url):
    """Dessine en-tête et pied de page sur chaque page (sauf couverture)."""
    canvas.saveState()
    page_num = doc.page

    # ── En-tête ──
    hx = MARGIN
    hy = PAGE_H - HEADER_H + 2 * mm
    hw = CONTENT_W

    canvas.setFillColor(NAVY)
    canvas.rect(0, PAGE_H - HEADER_H, PAGE_W, HEADER_H, fill=1, stroke=0)

    canvas.setFillColor(WHITE)
    canvas.setFont("Helvetica-Bold", 8)
    canvas.drawString(hx, hy, audit_name[:60])

    canvas.setFillColor(GREY)
    canvas.setFont("Helvetica", 7)
    url_text = audit_url[:70]
    canvas.drawRightString(PAGE_W - MARGIN, hy, url_text)

    # Ligne teal sous en-tête
    canvas.setStrokeColor(TEAL)
    canvas.setLineWidth(2.5)
    canvas.line(0, PAGE_H - HEADER_H, PAGE_W, PAGE_H - HEADER_H)

    # ── Pied de page ──
    canvas.setFillColor(LIGHT_BG)
    canvas.rect(0, 0, PAGE_W, FOOTER_H, fill=1, stroke=0)
    canvas.setStrokeColor(GREY)
    canvas.setLineWidth(0.5)
    canvas.line(MARGIN, FOOTER_H, PAGE_W - MARGIN, FOOTER_H)

    canvas.setFillColor(DARK_BLUE)
    canvas.setFont("Helvetica", 7)
    canvas.drawString(MARGIN, 4 * mm,
                      f"{audit_name}  ·  {datetime.now().strftime('%d/%m/%Y')}")

    canvas.setFillColor(GREY)
    canvas.setFont("Helvetica-Oblique", 7)
    canvas.drawCentredString(PAGE_W / 2, 4 * mm, "Confidentiel")

    canvas.setFillColor(DARK_BLUE)
    canvas.setFont("Helvetica", 7)
    canvas.drawRightString(PAGE_W - MARGIN, 4 * mm, f"Page {page_num}")

    canvas.restoreState()

# ─── PAGE DE COUVERTURE ───────────────────────────────────────────────────────
def build_cover(canvas, doc, audit_name, audit_url, target_type,
                audit_statut, counts, total, score, audit_date):
    canvas.saveState()
    W, H = PAGE_W, PAGE_H

    # Fond navy total
    canvas.setFillColor(NAVY)
    canvas.rect(0, 0, W, H, fill=1, stroke=0)

    # Bande teal en haut
    canvas.setFillColor(TEAL)
    canvas.rect(0, H - 22 * mm, W, 22 * mm, fill=1, stroke=0)

    # Barre accent gauche
    canvas.setFillColor(ACCENT)
    canvas.rect(0, 0, 4 * mm, H - 22 * mm, fill=1, stroke=0)

    # Tag rapport
    tag_txt = t("cover.tag")
    tag_w = 120 * mm
    tag_x = (W - tag_w) / 2
    tag_y = H - 55 * mm
    canvas.setFillColor(TEAL)
    canvas.roundRect(tag_x, tag_y, tag_w, 9 * mm, 3, fill=1, stroke=0)
    canvas.setFillColor(WHITE)
    canvas.setFont("Helvetica-Bold", 9)
    canvas.drawCentredString(W / 2, tag_y + 2.5 * mm, tag_txt)

    # Nom de l'audit (grand)
    canvas.setFillColor(WHITE)
    canvas.setFont("Helvetica-Bold", 26)
    # Tronquer si trop long
    name_display = audit_name if len(audit_name) <= 38 else audit_name[:36] + "…"
    canvas.drawCentredString(W / 2, H - 75 * mm, name_display)

    # Sous-titre
    canvas.setFillColor(GREY)
    canvas.setFont("Helvetica-Oblique", 10)
    canvas.drawCentredString(W / 2, H - 85 * mm, t("cover.subtitle"))

    # Ligne séparation teal
    canvas.setStrokeColor(TEAL)
    canvas.setLineWidth(1.5)
    canvas.line(MARGIN * 2, H - 91 * mm, W - MARGIN * 2, H - 91 * mm)

    # Label URL
    canvas.setFillColor(GREY)
    canvas.setFont("Helvetica", 8)
    canvas.drawCentredString(W / 2, H - 100 * mm, t("cover.url_label"))
    canvas.setFillColor(ACCENT)
    canvas.setFont("Helvetica-Bold", 9)
    url_disp = audit_url if len(audit_url) <= 60 else audit_url[:58] + "…"
    canvas.drawCentredString(W / 2, H - 108 * mm, url_disp)

    # ── Bandeau score ──
    bandeau_y = H - 170 * mm
    bandeau_h = 38 * mm
    bandeau_x = MARGIN * 2
    bandeau_w = W - 4 * MARGIN

    # Bloc gauche coloré (score)
    sc_color = score_color_m(score)
    left_w = bandeau_w * 0.38
    canvas.setFillColor(colors.HexColor(sc_color))
    canvas.roundRect(bandeau_x, bandeau_y, left_w, bandeau_h, 4, fill=1, stroke=0)

    # Score en grand (inline markup impossible sur canvas → on gère manuellement)
    score_str = format_score(score)
    canvas.setFillColor(WHITE)
    canvas.setFont("Helvetica-Bold", 34)
    score_x = bandeau_x + left_w * 0.45
    score_y = bandeau_y + bandeau_h * 0.38
    canvas.drawCentredString(score_x, score_y, score_str)
    if score is not None:
        canvas.setFont("Helvetica-Bold", 18)
        score_w = canvas.stringWidth(score_str, "Helvetica-Bold", 34)
        pct_x = score_x + score_w / 2 + 3
        canvas.drawString(pct_x, score_y + 5, "%")
    canvas.setFont("Helvetica", 8)
    canvas.drawCentredString(score_x, bandeau_y + bandeau_h * 0.82,
                             t("cover.score_label"))

    # Bloc droite dark blue (détails)
    right_x = bandeau_x + left_w + 1 * mm
    right_w = bandeau_w - left_w - 1 * mm
    canvas.setFillColor(DARK_BLUE)
    canvas.roundRect(right_x, bandeau_y, right_w, bandeau_h, 4, fill=1, stroke=0)

    line_h = bandeau_h / 4
    detail_items = [
        (t("cover.score_detail_conforme"),      counts["Conforme"],       M_GREEN),
        (t("cover.score_detail_non_conforme"),  counts["Non conforme"],   M_RED),
        (t("cover.score_detail_a_verifier"),    counts["A verifier"],     M_ORANGE),
        (t("cover.score_detail_non_applicable"),counts["Non applicable"], "#95A5A6"),
    ]
    for i, (lbl, val, col) in enumerate(detail_items):
        item_y = bandeau_y + bandeau_h - (i + 1) * line_h + line_h * 0.2
        canvas.setFillColor(colors.HexColor(col))
        canvas.setFont("Helvetica-Bold", 10)
        canvas.drawString(right_x + 5 * mm, item_y, str(val))
        canvas.setFillColor(WHITE)
        canvas.setFont("Helvetica", 8)
        canvas.drawString(right_x + 16 * mm, item_y, lbl)

    # ── Pied de couverture ──
    foot_y = 18 * mm
    canvas.setFillColor(GREY)
    canvas.setFont("Helvetica", 8)
    footer_items = [
        f"Date : {audit_date}",
        f"Type : {target_type}",
        f"Statut : {audit_statut}",
    ]
    for i, item in enumerate(footer_items):
        canvas.drawCentredString(W / 4 * (i + 1), foot_y, item)

    # Ligne séparation pied
    canvas.setStrokeColor(TEAL)
    canvas.setLineWidth(1)
    canvas.line(MARGIN * 2, foot_y + 8 * mm, W - MARGIN * 2, foot_y + 8 * mm)

    canvas.restoreState()

# ─── CORRECTION PAR RÈGLE ─────────────────────────────────────────────────────
def get_correction(num, famille):
    by_rule = LANG.get("corrections", {}).get("by_rule", {})
    generic_fam = LANG.get("corrections", {}).get("generic_by_family", {})
    fallback = LANG.get("corrections", {}).get("generic_fallback", "")
    if str(num) in by_rule:
        return by_rule[str(num)]
    if famille in generic_fam:
        return generic_fam[famille]
    return fallback

# ─── SECTION 1 — Contexte et objectifs ───────────────────────────────────────
def build_section1():
    elements = []
    elements.append(Paragraph(t("section1.title"), S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))
    elements.append(Paragraph(t("section1.intro"), S["normal"]))
    elements.append(Spacer(1, 6))

    # Tableau objectifs
    elements.append(Paragraph(t("section1.objectives_title"), S["h2"]))
    objectives = t("section1.objectives")
    obj_data = []
    for i, (titre, desc) in enumerate(objectives):
        bg = LIGHT_BG if i % 2 == 0 else WHITE
        obj_data.append([
            Paragraph(f"<b>{titre}</b>", S["bold"]),
            Paragraph(desc, S["normal"]),
        ])
    col_w = [CONTENT_W * 0.28, CONTENT_W * 0.72]
    obj_table = Table(obj_data, colWidths=col_w)
    obj_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (0, -1), LIGHT_BG),
        ("BACKGROUND", (1, 0), (1, -1), WHITE),
        ("ROWBACKGROUNDS", (0, 0), (-1, -1), [LIGHT_BG, WHITE]),
        ("GRID", (0, 0), (-1, -1), 0.3, GREY),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ]))
    elements.append(obj_table)
    return elements

# ─── SECTION 2 — Méthodologie ─────────────────────────────────────────────────
def build_section2(data):
    elements = []
    elements.append(Paragraph(t("section2.title"), S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))

    # Périmètre
    elements.append(Paragraph(t("section2.perimeter_title"), S["h2"]))
    audit_name = data[0]["audit"] if data else ""
    audit_url  = data[0]["url"]   if data else ""
    audit_type = data[0]["type"]  if data else ""
    audit_sta  = data[0]["statut_audit"] if data else ""

    keys   = t("section2.perimeter_keys")
    values = list(t("section2.perimeter_values"))
    # Surcharge avec données réelles
    dyn_map = {3: f"{len(data)} règles", 4: audit_type or values[4], 6: "CSV → PDF"}
    for idx, val in dyn_map.items():
        if idx < len(values):
            values[idx] = val

    peri_data = []
    for i, (k, v) in enumerate(zip(keys, values)):
        bg = LIGHT_BG if i % 2 == 0 else WHITE
        peri_data.append([
            Paragraph(f"<b>{k}</b>", S["bold"]),
            Paragraph(v, S["normal"]),
        ])
    col_w = [CONTENT_W * 0.35, CONTENT_W * 0.65]
    peri_table = Table(peri_data, colWidths=col_w)
    peri_table.setStyle(TableStyle([
        ("ROWBACKGROUNDS", (0, 0), (-1, -1), [LIGHT_BG, WHITE]),
        ("GRID", (0, 0), (-1, -1), 0.3, GREY),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ]))
    elements.append(peri_table)
    elements.append(Spacer(1, 8))

    # Statuts
    elements.append(Paragraph(t("section2.statuts_title"), S["h2"]))
    statuts_info = t("section2.statuts")
    stat_data = []
    for s, (label, desc) in zip(STATUTS, statuts_info):
        badge = Table(
            [[Paragraph(f"<b>{label}</b>",
                        ParagraphStyle("badge", fontName="Helvetica-Bold",
                                       fontSize=8, textColor=WHITE,
                                       alignment=TA_CENTER))]],
            colWidths=[32 * mm]
        )
        badge.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), STATUT_COLORS[s]),
            ("TOPPADDING", (0, 0), (-1, -1), 4),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
            ("LEFTPADDING", (0, 0), (-1, -1), 4),
            ("RIGHTPADDING", (0, 0), (-1, -1), 4),
            ("ALIGN", (0, 0), (-1, -1), "CENTER"),
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ]))
        stat_data.append([badge, Paragraph(desc, S["normal"])])

    stat_table = Table(stat_data, colWidths=[36 * mm, CONTENT_W - 36 * mm])
    stat_table.setStyle(TableStyle([
        ("ROWBACKGROUNDS", (0, 0), (-1, -1), [LIGHT_BG, WHITE]),
        ("GRID", (0, 0), (-1, -1), 0.3, GREY),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ]))
    elements.append(stat_table)
    elements.append(Spacer(1, 8))

    # Formule score
    elements.append(Paragraph(t("section2.score_title"), S["h2"]))
    formula_table = Table(
        [[Paragraph(t("section2.score_formula"), S["formula"])],
         [Paragraph(t("section2.score_note"),
                    ParagraphStyle("note", fontName="Helvetica-Oblique", fontSize=8,
                                   textColor=WHITE, alignment=TA_CENTER))]],
        colWidths=[CONTENT_W]
    )
    formula_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), ACCENT),
        ("BACKGROUND", (0, 1), (-1, 1), DARK_BLUE),
        ("TOPPADDING", (0, 0), (-1, -1), 8),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
        ("LEFTPADDING", (0, 0), (-1, -1), 12),
        ("RIGHTPADDING", (0, 0), (-1, -1), 12),
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
    ]))
    elements.append(formula_table)
    return elements

# ─── SECTION 3 — Synthèse des résultats ──────────────────────────────────────
def build_section3(counts, total, score, family_stats):
    elements = []
    elements.append(Paragraph(t("section3.title"), S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))

    # KPIs
    kpi_labels  = t("section3.kpi_labels")
    kpi_values  = [score, counts["Conforme"], counts["Non conforme"],
                   counts["A verifier"], counts["Non applicable"]]
    kpi_colors  = [score_color(score), GREEN, RED, ORANGE, GREY]
    kpi_display = [f"{format_score(score)}" + ("%" if score is not None else ""),
                   str(counts["Conforme"]), str(counts["Non conforme"]),
                   str(counts["A verifier"]), str(counts["Non applicable"])]

    kpi_cells = []
    for lbl, val, col in zip(kpi_labels, kpi_display, kpi_colors):
        cell = Table(
            [[Paragraph(f"<b>{val}</b>",
                        ParagraphStyle("kpiv", fontName="Helvetica-Bold",
                                       fontSize=16, textColor=col,
                                       alignment=TA_CENTER))],
             [Paragraph(lbl, ParagraphStyle("kpil", fontName="Helvetica",
                                            fontSize=7, textColor=GREY,
                                            alignment=TA_CENTER))]],
            colWidths=[CONTENT_W / 5 - 2 * mm]
        )
        cell.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), WHITE),
            ("BOX", (0, 0), (-1, -1), 0.5, GREY),
            ("TOPPADDING", (0, 0), (-1, -1), 6),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
            ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ]))
        kpi_cells.append(cell)

    kpi_row = Table([kpi_cells], colWidths=[CONTENT_W / 5] * 5)
    kpi_row.setStyle(TableStyle([
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("LEFTPADDING", (0, 0), (-1, -1), 1),
        ("RIGHTPADDING", (0, 0), (-1, -1), 1),
    ]))
    elements.append(kpi_row)
    elements.append(Spacer(1, 10))

    # Graphiques camembert + jauge côte à côte
    elements.append(Paragraph("Graphiques", S["h2"]))
    elements.append(build_status_distribution_table(counts, total))
    elements.append(Spacer(1, 8))
    elements.append(build_family_distribution_table(family_stats))

    return elements

# ─── SECTION 4 — Résultats par famille ───────────────────────────────────────
def build_section4(family_stats, counts, total, score):
    elements = []
    elements.append(Paragraph(t("section4.title"), S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))

    headers = t("section4.table_headers")
    hdr_row = [Paragraph(f"<b>{h}</b>",
                          ParagraphStyle("hdr", fontName="Helvetica-Bold",
                                         fontSize=8, textColor=WHITE,
                                         alignment=TA_CENTER))
               for h in headers]

    col_w = [CONTENT_W * 0.26, CONTENT_W * 0.10, CONTENT_W * 0.12,
             CONTENT_W * 0.10, CONTENT_W * 0.10, CONTENT_W * 0.10, CONTENT_W * 0.12]

    rows = [hdr_row]
    for i, fam in enumerate(FAMILIES):
        fs = family_stats[fam]
        sc = fs["score"]
        sc_col = score_color(sc)
        bg = LIGHT_BG if i % 2 == 0 else WHITE

        badge_style = ParagraphStyle("sc_badge", fontName="Helvetica-Bold",
                                     fontSize=8, textColor=sc_col,
                                     alignment=TA_CENTER)
        row = [
            Paragraph(fam, S["normal"]),
            Paragraph(str(fs["Conforme"]),       S["center"]),
            Paragraph(str(fs["Non conforme"]),   S["center"]),
            Paragraph(str(fs["A verifier"]),     S["center"]),
            Paragraph(str(fs["Non applicable"]), S["center"]),
            Paragraph(str(fs["total"]),          S["center"]),
            Paragraph(f"<b>{format_score(sc)}" + ("%" if sc is not None else "") + "</b>", badge_style),
        ]
        rows.append(row)

    # Ligne total
    sc_total = score
    sc_col_total = score_color(sc_total)
    total_label = t("section4.total_label")
    total_badge_style = ParagraphStyle("tot_badge", fontName="Helvetica-Bold",
                                       fontSize=8, textColor=WHITE,
                                       alignment=TA_CENTER)
    total_row = [
        Paragraph(f"<b>{total_label}</b>",
                  ParagraphStyle("tot", fontName="Helvetica-Bold", fontSize=8,
                                 textColor=WHITE)),
        Paragraph(f"<b>{counts['Conforme']}</b>",
                  ParagraphStyle("tc", fontName="Helvetica-Bold", fontSize=8,
                                 textColor=WHITE, alignment=TA_CENTER)),
        Paragraph(f"<b>{counts['Non conforme']}</b>",
                  ParagraphStyle("tnc", fontName="Helvetica-Bold", fontSize=8,
                                 textColor=WHITE, alignment=TA_CENTER)),
        Paragraph(f"<b>{counts['A verifier']}</b>",
                  ParagraphStyle("tav", fontName="Helvetica-Bold", fontSize=8,
                                 textColor=WHITE, alignment=TA_CENTER)),
        Paragraph(f"<b>{counts['Non applicable']}</b>",
                  ParagraphStyle("tna", fontName="Helvetica-Bold", fontSize=8,
                                 textColor=WHITE, alignment=TA_CENTER)),
        Paragraph(f"<b>{total}</b>",
                  ParagraphStyle("tt", fontName="Helvetica-Bold", fontSize=8,
                                 textColor=WHITE, alignment=TA_CENTER)),
        Paragraph(f"<b>{format_score(sc_total)}" + ("%" if sc_total is not None else "") + "</b>", total_badge_style),
    ]
    rows.append(total_row)

    fam_table = Table(rows, colWidths=col_w, repeatRows=1)
    n = len(rows)
    style_cmds = [
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("BACKGROUND", (0, n-1), (-1, n-1), NAVY),
        ("ROWBACKGROUNDS", (0, 1), (-1, n-2), [LIGHT_BG, WHITE]),
        ("GRID", (0, 0), (-1, -1), 0.3, GREY),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("ALIGN", (1, 0), (-1, -1), "CENTER"),
    ]
    fam_table.setStyle(TableStyle(style_cmds))
    elements.append(fam_table)
    elements.append(Spacer(1, 6))

    # Légende
    legend_items = [
        (GREEN, t("section4.legend_green")),
        (ORANGE, t("section4.legend_orange")),
        (RED, t("section4.legend_red")),
    ]
    leg_cells = []
    for col, lbl in legend_items:
        patch = Table([[""]], colWidths=[5 * mm])
        patch.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), col),
            ("TOPPADDING", (0, 0), (-1, -1), 5),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ]))
        leg_cells.extend([patch, Paragraph(f" {lbl}", S["small"])])
    leg_row = Table([leg_cells],
                    colWidths=[6 * mm, 45 * mm] * 3)
    leg_row.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("LEFTPADDING", (0, 0), (-1, -1), 2),
    ]))
    elements.append(leg_row)
    return elements

# ─── SECTION 5 — Non-conformités ─────────────────────────────────────────────
def build_section5(data):
    elements = []
    elements.append(Paragraph(t("section5.title"), S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))

    non_conf = [r for r in data if r["statut"] == "Non conforme"]

    if not non_conf:
        elements.append(Paragraph(t("section5.no_nonconformities"), S["normal"]))
        return elements

    # Bandeau rouge
    banner = Table(
        [[Paragraph(
            f"<b>{t('section5.banner_text', count=len(non_conf))}</b>",
            ParagraphStyle("banner", fontName="Helvetica-Bold", fontSize=10,
                           textColor=WHITE, alignment=TA_CENTER)
        )]],
        colWidths=[CONTENT_W]
    )
    banner.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), RED),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
    ]))
    elements.append(banner)
    elements.append(Spacer(1, 8))

    for rule in non_conf:
        num     = rule["num"]
        fam     = rule["famille"]
        intit   = rule["intitule"]
        comment = rule["commentaire"] or "—"
        source  = rule["source"]
        correc  = get_correction(num, fam)

        # En-tête rouge
        hdr = Table(
            [[Paragraph(
                f"<b>{t('section5.rule_label')}{num} · {fam}</b>",
                S["rule_title"]
            )]],
            colWidths=[CONTENT_W]
        )
        hdr.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), RED),
            ("TOPPADDING", (0, 0), (-1, -1), 5),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ("LEFTPADDING", (0, 0), (-1, -1), 8),
            ("RIGHTPADDING", (0, 0), (-1, -1), 8),
        ]))

        # Intitulé
        intit_cell = Table(
            [[Paragraph(intit, S["intitule"])]],
            colWidths=[CONTENT_W]
        )
        intit_cell.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), WHITE),
            ("TOPPADDING", (0, 0), (-1, -1), 6),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
            ("LEFTPADDING", (0, 0), (-1, -1), 8),
            ("RIGHTPADDING", (0, 0), (-1, -1), 8),
            ("BOX", (0, 0), (-1, -1), 0.3, GREY),
        ]))

        # Constat
        constat_cell = Table(
            [[Paragraph(
                f"<font color='#C0392B'><b>{t('section5.constat_label')} :</b></font> {comment}",
                S["normal"]
            )]],
            colWidths=[CONTENT_W]
        )
        constat_cell.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), RED_PALE),
            ("TOPPADDING", (0, 0), (-1, -1), 5),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ("LEFTPADDING", (0, 0), (-1, -1), 8),
            ("RIGHTPADDING", (0, 0), (-1, -1), 8),
            ("BOX", (0, 0), (-1, -1), 0.3, GREY),
        ]))

        # Correction
        correc_cell = Table(
            [[Paragraph(
                f"<font color='#1E8449'><b>{t('section5.correction_label')} :</b></font> {correc}",
                S["normal"]
            )]],
            colWidths=[CONTENT_W]
        )
        correc_cell.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), GREEN_PALE),
            ("TOPPADDING", (0, 0), (-1, -1), 5),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ("LEFTPADDING", (0, 0), (-1, -1), 8),
            ("RIGHTPADDING", (0, 0), (-1, -1), 8),
            ("BOX", (0, 0), (-1, -1), 0.3, GREY),
        ]))

        group = [hdr, intit_cell, constat_cell, correc_cell]

        # Source/doc si présente
        if source and source.startswith("http"):
            doc_cell = Table(
                [[Paragraph(
                    f"<font color='#3498DB'><b>{t('section5.doc_label')} :</b></font>"
                    f" <a href='{source}'>{source[:70]}</a>",
                    S["normal"]
                )]],
                colWidths=[CONTENT_W]
            )
            doc_cell.setStyle(TableStyle([
                ("BACKGROUND", (0, 0), (-1, -1), BLUE_PALE),
                ("TOPPADDING", (0, 0), (-1, -1), 4),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
                ("LEFTPADDING", (0, 0), (-1, -1), 8),
                ("RIGHTPADDING", (0, 0), (-1, -1), 8),
                ("BOX", (0, 0), (-1, -1), 0.3, GREY),
            ]))
            group.append(doc_cell)

        elements.append(KeepTogether(group))
        elements.append(Spacer(1, 8))

    return elements

# ─── SECTION 6 — Plan d'action ────────────────────────────────────────────────
def build_section6(data, family_stats):
    elements = []
    elements.append(Paragraph(t("section6.title"), S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))

    non_conf = [r for r in data if r["statut"] == "Non conforme"]

    # Tableau non-conformités priorisées
    elements.append(Paragraph(t("section6.nc_table_title"), S["h2"]))
    nc_headers = t("section6.nc_table_headers")
    nc_col_w = [CONTENT_W * 0.08, CONTENT_W * 0.09,
                CONTENT_W * 0.18, CONTENT_W * 0.33, CONTENT_W * 0.32]

    nc_hdr_row = [Paragraph(f"<b>{h}</b>",
                             ParagraphStyle("nchdr", fontName="Helvetica-Bold",
                                            fontSize=8, textColor=WHITE,
                                            alignment=TA_CENTER))
                  for h in nc_headers]
    nc_rows = [nc_hdr_row]
    for i, rule in enumerate(non_conf):
        prio = f"P{i+1}"
        comment = rule["commentaire"][:60] + "…" if len(rule.get("commentaire","")) > 60 else rule.get("commentaire","—")
        nc_rows.append([
            Paragraph(f"<b>{prio}</b>", S["center_bold"]),
            Paragraph(rule["num"], S["center"]),
            Paragraph(rule["famille"], S["normal"]),
            Paragraph(rule["intitule"][:80], S["normal"]),
            Paragraph(comment, S["normal"]),
        ])

    nc_table = Table(nc_rows, colWidths=nc_col_w, repeatRows=1)
    n_nc = len(nc_rows)
    nc_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [RED_PALE, WHITE]),
        ("GRID", (0, 0), (-1, -1), 0.3, GREY),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
    ]))
    elements.append(nc_table)
    elements.append(Spacer(1, 10))

    # Top 5 familles À vérifier
    elements.append(Paragraph(t("section6.av_table_title"), S["h2"]))
    av_headers = t("section6.av_table_headers")
    av_col_w = [CONTENT_W * 0.08, CONTENT_W * 0.30,
                CONTENT_W * 0.15, CONTENT_W * 0.47]

    av_hdr_row = [Paragraph(f"<b>{h}</b>",
                             ParagraphStyle("avhdr", fontName="Helvetica-Bold",
                                            fontSize=8, textColor=WHITE,
                                            alignment=TA_CENTER))
                  for h in av_headers]

    fam_av = sorted(
        [(fam, family_stats[fam]["A verifier"]) for fam in FAMILIES
         if family_stats[fam]["A verifier"] > 0],
        key=lambda x: x[1], reverse=True
    )[:5]

    av_rows = [av_hdr_row]
    for i, (fam, cnt) in enumerate(fam_av):
        av_rows.append([
            Paragraph(f"<b>#{i+1}</b>", S["center_bold"]),
            Paragraph(fam, S["normal"]),
            Paragraph(str(cnt), S["center"]),
            Paragraph(t("section6.av_action"), S["normal"]),
        ])

    if len(av_rows) > 1:
        av_table = Table(av_rows, colWidths=av_col_w, repeatRows=1)
        av_table.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, 0), NAVY),
            ("ROWBACKGROUNDS", (0, 1), (-1, -1),
             [colors.HexColor("#FEF9E7"), WHITE]),
            ("GRID", (0, 0), (-1, -1), 0.3, GREY),
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("TOPPADDING", (0, 0), (-1, -1), 5),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ("LEFTPADDING", (0, 0), (-1, -1), 5),
            ("RIGHTPADDING", (0, 0), (-1, -1), 5),
        ]))
        elements.append(av_table)

    return elements

# ─── SECTION 7 — Recommandations générales ───────────────────────────────────
def build_section7():
    elements = []
    elements.append(Paragraph(t("section7.title"), S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))
    elements.append(Paragraph(t("section7.intro"), S["normal"]))
    elements.append(Spacer(1, 6))

    recs = t("section7.recommendations")
    rec_data = []
    for i, (titre, desc) in enumerate(recs):
        # Barre accent gauche simulée via fond ACCENT sur 1ère colonne
        rec_data.append([
            Paragraph("", S["normal"]),
            Paragraph(f"<b>{titre}</b>", S["bold"]),
            Paragraph(desc, S["normal"]),
        ])

    rec_table = Table(rec_data, colWidths=[3 * mm, CONTENT_W * 0.28, CONTENT_W * 0.68])
    rec_table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (0, -1), ACCENT),
        ("ROWBACKGROUNDS", (1, 0), (-1, -1), [LIGHT_BG, WHITE]),
        ("GRID", (1, 0), (-1, -1), 0.3, GREY),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
        ("LEFTPADDING", (1, 0), (-1, -1), 8),
        ("RIGHTPADDING", (1, 0), (-1, -1), 8),
        ("LEFTPADDING", (0, 0), (0, -1), 0),
        ("RIGHTPADDING", (0, 0), (0, -1), 0),
    ]))
    elements.append(rec_table)
    return elements

# ─── SECTION 8 — Annexe complète ─────────────────────────────────────────────
def build_section8(data):
    elements = []
    elements.append(Paragraph(t("section8.title"), S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=4))
    elements.append(Paragraph(
        t("section8.intro", total=len(data)), S["normal"]
    ))
    elements.append(Spacer(1, 6))

    headers = t("section8.table_headers")
    col_w = [CONTENT_W * 0.09, CONTENT_W * 0.73, CONTENT_W * 0.18]

    # Grouper par famille
    by_family = {f: [] for f in FAMILIES}
    for row in data:
        fam = row["famille"]
        if fam in by_family:
            by_family[fam].append(row)
        else:
            # Famille inconnue → on l'ajoute quand même
            if fam not in by_family:
                by_family[fam] = []
            by_family[fam].append(row)

    for fam in FAMILIES:
        rules = by_family.get(fam, [])
        if not rules:
            continue

        elements.append(Paragraph(fam, S["h2"]))

        hdr_row = [Paragraph(f"<b>{h}</b>",
                              ParagraphStyle("ah", fontName="Helvetica-Bold",
                                             fontSize=8, textColor=WHITE))
                   for h in headers]
        rows = [hdr_row]
        for i, rule in enumerate(rules):
            statut = rule["statut"]
            statut_label = t(f"statut_labels.{statut}")
            sc = STATUT_COLORS.get(statut, GREY)
            rows.append([
                Paragraph(rule["num"],
                          ParagraphStyle("anum", fontName="Helvetica", fontSize=8,
                                         textColor=DARK_BLUE)),
                Paragraph(rule["intitule"],
                          ParagraphStyle("aintit", fontName="Helvetica", fontSize=8,
                                         textColor=DARK_BLUE, leading=11)),
                Paragraph(f"<b>{statut_label}</b>",
                          ParagraphStyle("ast", fontName="Helvetica-Bold", fontSize=8,
                                         textColor=sc)),
            ])

        fam_table = Table(rows, colWidths=col_w, repeatRows=1)
        n = len(rows)
        fam_table.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, 0), DARK_BLUE),
            ("ROWBACKGROUNDS", (0, 1), (-1, -1), [LIGHT_BG, WHITE]),
            ("GRID", (0, 0), (-1, -1), 0.3, GREY),
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("TOPPADDING", (0, 0), (-1, -1), 3),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
            ("LEFTPADDING", (0, 0), (-1, -1), 5),
            ("RIGHTPADDING", (0, 0), (-1, -1), 5),
        ]))
        elements.append(fam_table)
        elements.append(Spacer(1, 6))

    return elements

def build_site_section1(site_ctx):
    elements = []
    elements.append(Paragraph("1. Contexte et objectifs de l'audit", S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))
    elements.append(Paragraph(
        "Cet audit de type Site consolide plusieurs URLs auditees individuellement. "
        "Chaque URL conserve ses 245 regles, ses propres statuts et son propre parcours d'evaluation.",
        S["normal"]
    ))
    elements.append(Spacer(1, 6))
    elements.append(Paragraph("URLs auditees", S["h2"]))

    rows = [[
        Paragraph("<b>#</b>", S["bold_white"]),
        Paragraph("<b>URL</b>", S["bold_white"]),
        Paragraph("<b>Regles</b>", S["bold_white"]),
    ]]

    for url_data in site_ctx["urls"]:
        rows.append([
            Paragraph(str(url_data["index"]), S["center"]),
            Paragraph(url_data["url"], S["normal"]),
            Paragraph(str(url_data["total"]), S["center"]),
        ])

    table = Table(rows, colWidths=[CONTENT_W * 0.08, CONTENT_W * 0.70, CONTENT_W * 0.12], repeatRows=1)
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [LIGHT_BG, WHITE]),
        ("GRID", (0, 0), (-1, -1), 0.3, GREY),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
    ]))
    elements.append(table)
    return elements

def build_site_section2(audit_type, total_rows, url_count):
    elements = []
    elements.append(Paragraph("2. Methodologie", S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))
    elements.append(Paragraph(
        "Le mode Site agrege les resultats de plusieurs audits URL enfants. "
        "Le detail reste evalue page par page, tandis que le score principal du rapport est calcule globalement.",
        S["normal"]
    ))
    elements.append(Spacer(1, 6))

    peri_data = [
        [Paragraph("<b>Type de cible</b>", S["bold"]), Paragraph(audit_type or "Site", S["normal"])],
        [Paragraph("<b>Nombre d'URLs auditees</b>", S["bold"]), Paragraph(str(url_count), S["normal"])],
        [Paragraph("<b>Nombre total de lignes evaluees</b>", S["bold"]), Paragraph(str(total_rows), S["normal"])],
        [Paragraph("<b>Mode de calcul global</b>", S["bold"]), Paragraph("Somme des Conformes / (Somme Conformes + Somme Non conformes) x 100", S["normal"])],
    ]
    table = Table(peri_data, colWidths=[CONTENT_W * 0.30, CONTENT_W * 0.70])
    table.setStyle(TableStyle([
        ("ROWBACKGROUNDS", (0, 0), (-1, -1), [LIGHT_BG, WHITE]),
        ("GRID", (0, 0), (-1, -1), 0.3, GREY),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ]))
    elements.append(table)
    return elements

def build_site_section3(site_ctx):
    elements = []
    counts = site_ctx["counts"]
    total = site_ctx["total"]
    score = site_ctx["score"]
    elements.append(Paragraph("3. Synthese des resultats", S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))
    elements.append(build_status_distribution_table(counts, total))
    elements.append(Spacer(1, 8))
    elements.append(build_family_distribution_table(site_ctx["family_stats"]))
    return elements

def build_site_section4(site_ctx):
    elements = []
    elements.append(Paragraph("4. Comparatif des URLs auditees", S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))

    rows = [[
        Paragraph("<b>URL</b>", S["bold_white"]),
        Paragraph("<b>Conforme</b>", S["bold_white"]),
        Paragraph("<b>Non conforme</b>", S["bold_white"]),
        Paragraph("<b>A verifier</b>", S["bold_white"]),
        Paragraph("<b>N/A</b>", S["bold_white"]),
        Paragraph("<b>Score</b>", S["bold_white"]),
    ]]

    for url_data in site_ctx["urls"]:
        counts = url_data["counts"]
        rows.append([
            Paragraph(url_data["url"], S["normal"]),
            Paragraph(str(counts["Conforme"]), S["center"]),
            Paragraph(str(counts["Non conforme"]), S["center"]),
            Paragraph(str(counts["A verifier"]), S["center"]),
            Paragraph(str(counts["Non applicable"]), S["center"]),
            Paragraph(audit_opquast_site_score_label_py(url_data["score"]), S["center"]),
        ])

    table = Table(
        rows,
        colWidths=[CONTENT_W * 0.42, CONTENT_W * 0.11, CONTENT_W * 0.13, CONTENT_W * 0.11, CONTENT_W * 0.11, CONTENT_W * 0.12],
        repeatRows=1
    )
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [LIGHT_BG, WHITE]),
        ("GRID", (0, 0), (-1, -1), 0.3, GREY),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
    ]))
    elements.append(table)
    return elements

def build_site_section5(data):
    elements = []
    elements.append(Paragraph("5. Non-conformites et recommandations", S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))
    non_conf = [r for r in data if r["statut"] == "Non conforme"]

    if not non_conf:
        elements.append(Paragraph("Aucune regle non conforme n'a ete detectee sur les URLs auditees.", S["normal"]))
        return elements

    rows = [[
        Paragraph("<b>No</b>", S["bold_white"]),
        Paragraph("<b>URL</b>", S["bold_white"]),
        Paragraph("<b>Famille</b>", S["bold_white"]),
        Paragraph("<b>Intitule</b>", S["bold_white"]),
        Paragraph("<b>Correction recommandee</b>", S["bold_white"]),
    ]]

    for rule in non_conf:
        rows.append([
            Paragraph(rule["num"], S["center"]),
            Paragraph(shorten_url(rule["url"]), S["normal"]),
            Paragraph(rule["famille"], S["normal"]),
            Paragraph(rule["intitule"], S["normal"]),
            Paragraph(get_correction(rule["num"], rule["famille"]), S["normal"]),
        ])

    table = Table(
        rows,
        colWidths=[CONTENT_W * 0.07, CONTENT_W * 0.20, CONTENT_W * 0.16, CONTENT_W * 0.27, CONTENT_W * 0.30],
        repeatRows=1
    )
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), RED),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [RED_PALE, WHITE]),
        ("GRID", (0, 0), (-1, -1), 0.3, GREY),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
    ]))
    elements.append(table)
    return elements

def build_site_section6(site_ctx):
    elements = []
    elements.append(Paragraph("6. Plan d'action priorise", S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))

    rows = [[
        Paragraph("<b>#</b>", S["bold_white"]),
        Paragraph("<b>URL</b>", S["bold_white"]),
        Paragraph("<b>Score</b>", S["bold_white"]),
        Paragraph("<b>Non conformes</b>", S["bold_white"]),
        Paragraph("<b>A verifier</b>", S["bold_white"]),
    ]]

    ranked = sorted(site_ctx["urls"], key=lambda item: ((item["score"] if item["score"] is not None else -1), -item["counts"]["Non conforme"]))

    for index, url_data in enumerate(ranked, start=1):
        counts = url_data["counts"]
        rows.append([
            Paragraph(f"<b>P{index}</b>", S["center_bold"]),
            Paragraph(url_data["url"], S["normal"]),
            Paragraph(audit_opquast_site_score_label_py(url_data["score"]), S["center"]),
            Paragraph(str(counts["Non conforme"]), S["center"]),
            Paragraph(str(counts["A verifier"]), S["center"]),
        ])

    table = Table(rows, colWidths=[CONTENT_W * 0.08, CONTENT_W * 0.50, CONTENT_W * 0.14, CONTENT_W * 0.14, CONTENT_W * 0.14], repeatRows=1)
    table.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [LIGHT_BG, WHITE]),
        ("GRID", (0, 0), (-1, -1), 0.3, GREY),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
    ]))
    elements.append(table)
    return elements

def build_site_section7():
    return build_section7()

def build_site_section8(site_ctx):
    elements = []
    elements.append(Paragraph("8. Annexe - Detail par URL", S["h1"]))
    elements.append(HRFlowable(width=CONTENT_W, thickness=2, color=TEAL, spaceAfter=6))
    elements.append(Paragraph(
        "Cette annexe detaille les regles par URL auditee, toujours dans la limite de 245 regles par page.",
        S["normal"]
    ))
    elements.append(Spacer(1, 8))

    for url_data in site_ctx["urls"]:
        elements.append(Paragraph(url_data["url"], S["h2"]))
        elements.append(Paragraph(
            f"Score : {audit_opquast_site_score_label_py(url_data['score'])} - "
            f"Conforme : {url_data['counts']['Conforme']} - "
            f"Non conforme : {url_data['counts']['Non conforme']} - "
            f"A verifier : {url_data['counts']['A verifier']} - "
            f"N/A : {url_data['counts']['Non applicable']}",
            S["normal"]
        ))
        elements.append(Spacer(1, 4))

        headers = [
            Paragraph("<b>No</b>", S["bold_white"]),
            Paragraph("<b>Intitule</b>", S["bold_white"]),
            Paragraph("<b>Statut</b>", S["bold_white"]),
        ]
        rows = [headers]

        for rule in url_data["rows"]:
            rows.append([
                Paragraph(rule["num"], S["center"]),
                Paragraph(rule["intitule"], S["normal"]),
                Paragraph(t(f"statut_labels.{rule['statut']}"), S["center"]),
            ])

        table = Table(rows, colWidths=[CONTENT_W * 0.09, CONTENT_W * 0.71, CONTENT_W * 0.20], repeatRows=1)
        table.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, 0), DARK_BLUE),
            ("ROWBACKGROUNDS", (0, 1), (-1, -1), [LIGHT_BG, WHITE]),
            ("GRID", (0, 0), (-1, -1), 0.3, GREY),
            ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ("TOPPADDING", (0, 0), (-1, -1), 3),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
            ("LEFTPADDING", (0, 0), (-1, -1), 5),
            ("RIGHTPADDING", (0, 0), (-1, -1), 5),
        ]))
        elements.append(table)
        elements.append(Spacer(1, 10))

    return elements

def audit_opquast_site_score_label_py(score):
    return format_score(score) + ("%" if score is not None else "")

def generate_site_pdf(data, pdf_path, audit_name, audit_sta, audit_date):
    site_ctx = compute_site_context(data)
    counts = site_ctx["counts"]
    total = site_ctx["total"]
    score = site_ctx["score"]
    audit_url = f"{len(site_ctx['urls'])} URL(s) auditees"
    audit_type = data[0]["type"] if data else "Site"

    print(f"[Opquast PDF] {len(data)} lignes site - {len(site_ctx['urls'])} URL(s) - score {format_score(score)}" + ("%" if score is not None else ""))

    def on_first_page(canvas, doc):
        build_cover(canvas, doc, audit_name, audit_url, audit_type,
                    audit_sta, counts, total, score, audit_date)

    def on_later_pages(canvas, doc):
        on_page(canvas, doc, audit_name, audit_url)

    doc = SimpleDocTemplate(
        pdf_path,
        pagesize=A4,
        leftMargin=MARGIN,
        rightMargin=MARGIN,
        topMargin=HEADER_H + 8 * mm,
        bottomMargin=FOOTER_H + 8 * mm,
        title=f"Rapport Audit Opquast - {audit_name}",
        author="Plugin audit_opquast / SPIP",
        subject="Audit qualite web Opquast multi-URL",
    )

    story = []
    story.append(PageBreak())
    story.extend(build_site_section1(site_ctx))
    story.append(PageBreak())
    story.extend(build_site_section2(audit_type, len(data), len(site_ctx["urls"])))
    story.append(PageBreak())
    story.extend(build_site_section3(site_ctx))
    story.append(PageBreak())
    story.extend(build_site_section4(site_ctx))
    story.append(PageBreak())
    story.extend(build_site_section5(data))
    story.append(PageBreak())
    story.extend(build_site_section6(site_ctx))
    story.append(PageBreak())
    story.extend(build_site_section7())
    story.append(PageBreak())
    story.extend(build_site_section8(site_ctx))

    doc.build(story, onFirstPage=on_first_page, onLaterPages=on_later_pages)
    print(f"[Opquast PDF] PDF site genere : {pdf_path}")

# DOCUMENT BUILDER
def generate_pdf(csv_path, pdf_path):
    print(f"[Opquast PDF] Lecture CSV : {csv_path}")
    rows = load_csv(csv_path)
    if not rows:
        print("[ERREUR] CSV vide ou illisible.")
        sys.exit(1)

    data = parse_data(rows)
    audit_name = data[0]["audit"] if data else "Audit Opquast"
    audit_url  = data[0]["url"]   if data else ""
    audit_type = data[0]["type"]  if data else ""
    audit_sta  = data[0]["statut_audit"] if data else ""
    audit_date = datetime.now().strftime("%d/%m/%Y")

    if audit_type.lower() == "site":
        generate_site_pdf(data, pdf_path, audit_name, audit_sta, audit_date)
        return

    counts, total, score = compute_stats(data)
    family_stats = compute_family_stats(data)

    print(f"[Opquast PDF] {len(data)} regles - score {format_score(score)}" + ("%" if score is not None else ""))

    def on_first_page(canvas, doc):
        build_cover(canvas, doc, audit_name, audit_url, audit_type,
                    audit_sta, counts, total, score, audit_date)

    def on_later_pages(canvas, doc):
        on_page(canvas, doc, audit_name, audit_url)

    doc = SimpleDocTemplate(
        pdf_path,
        pagesize=A4,
        leftMargin=MARGIN,
        rightMargin=MARGIN,
        topMargin=HEADER_H + 8 * mm,
        bottomMargin=FOOTER_H + 8 * mm,
        title=f"Rapport Audit Opquast - {audit_name}",
        author="Plugin audit_opquast / SPIP",
        subject="Audit qualite web Opquast 245 regles",
    )

    story = []
    story.append(PageBreak())
    story.extend(build_section1())
    story.append(PageBreak())
    story.extend(build_section2(data))
    story.append(PageBreak())
    story.extend(build_section3(counts, total, score, family_stats))
    story.append(PageBreak())
    story.extend(build_section4(family_stats, counts, total, score))
    story.append(PageBreak())
    story.extend(build_section5(data))
    story.append(PageBreak())
    story.extend(build_section6(data, family_stats))
    story.append(PageBreak())
    story.extend(build_section7())
    story.append(PageBreak())
    story.extend(build_section8(data))

    doc.build(story, onFirstPage=on_first_page, onLaterPages=on_later_pages)
    print(f"[Opquast PDF] PDF genere : {pdf_path}")

if __name__ == "__main__":
    if len(sys.argv) >= 3:
        csv_in  = sys.argv[1]
        pdf_out = sys.argv[2]
    elif len(sys.argv) == 2:
        csv_in  = sys.argv[1]
        pdf_out = DEFAULT_PDF
    else:
        csv_in  = DEFAULT_CSV
        pdf_out = DEFAULT_PDF

    if not os.path.isfile(csv_in):
        print(f"[ERREUR] Fichier CSV introuvable : {csv_in}")
        sys.exit(1)

    generate_pdf(csv_in, pdf_out)
