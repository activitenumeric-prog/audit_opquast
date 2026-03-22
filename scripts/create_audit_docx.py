#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Generateur DOCX Opquast a partir d'un vrai template Word.

Le package .docx de reference est conserve (styles, theme, entetes, pieds,
numerotation, medias, etc.) mais le corps du document est reconstruit en
WordprocessingML natif afin d'eviter l'approche altChunk.
"""

import csv
import os
import re
import sys
import zipfile
from datetime import datetime
from xml.sax.saxutils import escape as xml_escape


BASE_DIR = os.path.dirname(__file__)
TEMPLATE_DOCX = os.path.join(BASE_DIR, "rapport_audit_opquast_template.docx")
DEFAULT_CSV = os.path.join(BASE_DIR, "_docx_test.csv")
DEFAULT_DOCX = os.path.join(BASE_DIR, "_docx_test.docx")

DOC_WIDTH = 9638
HALF_WIDTH = 4819
STATUSES = ["Conforme", "Non conforme", "A verifier", "Non applicable"]
STATUS_SOFT = {
    "Conforme": "EAF7F0",
    "Non conforme": "FDEDEC",
    "A verifier": "FEF5E7",
    "Non applicable": "F4F6F7",
}

FRENCH_MONTHS = {
    1: "janvier",
    2: "fevrier",
    3: "mars",
    4: "avril",
    5: "mai",
    6: "juin",
    7: "juillet",
    8: "aout",
    9: "septembre",
    10: "octobre",
    11: "novembre",
    12: "decembre",
}


def load_csv(path):
    for encoding in ("utf-8-sig", "utf-8", "latin-1"):
        try:
            with open(path, encoding=encoding, newline="") as handle:
                return [
                    {(key or "").strip(): (value or "").strip() for key, value in row.items()}
                    for row in csv.DictReader(handle, delimiter=";")
                ]
        except Exception:
            continue
    return []


def normalize_status(label):
    value = (label or "").strip()
    if value in ("\u00c0 v\u00e9rifier", "A v\u00e9rifier", "A verifier"):
        return "A verifier"
    aliases = {
        "À vérifier": "A verifier",
        "A vérifier": "A verifier",
        "A verifier": "A verifier",
        "Ã€ vÃ©rifier": "A verifier",
    }
    return aliases.get(value, value)


def parse_rows(rows):
    parsed = []
    for row in rows:
        parsed.append(
            {
                "audit": row.get("Audit", ""),
                "url": row.get("URL cible", ""),
                "type": row.get("Type de cible", ""),
                "statut_audit": row.get("Statut audit", ""),
                "num": row.get("Numero regle", ""),
                "intitule": row.get("Intitule", ""),
                "famille": row.get("Famille", "") or "Famille non renseignee",
                "statut": normalize_status(row.get("Statut", "")),
                "commentaire": row.get("Commentaire", ""),
                "preuve": row.get("Preuve ou note", ""),
                "source": row.get("Source", ""),
            }
        )
    return parsed


def summarize(data):
    counts = {status: 0 for status in STATUSES}
    for row in data:
        if row["statut"] in counts:
            counts[row["statut"]] += 1

    total = sum(counts.values())
    treated = counts["Conforme"] + counts["Non conforme"] + counts["Non applicable"]
    scored = counts["Conforme"] + counts["Non conforme"]
    score = round((counts["Conforme"] / scored) * 100, 2) if scored else None
    progress = round((treated / total) * 100, 2) if total else 0.0

    return {
        "counts": counts,
        "total": total,
        "treated": treated,
        "score": score,
        "progress": progress,
    }


def build_family_summary(data):
    families = {}
    for row in data:
        family = row["famille"]
        if family not in families:
            families[family] = {status: 0 for status in STATUSES}
        if row["statut"] in STATUSES:
            families[family][row["statut"]] += 1

    for family, values in families.items():
        values["total"] = sum(values[status] for status in STATUSES)
        scored = values["Conforme"] + values["Non conforme"]
        values["score"] = round((values["Conforme"] / scored) * 100, 2) if scored else None

    return families


def build_site_context(data):
    urls = {}
    order = []
    for row in data:
        url = row["url"]
        if url not in urls:
            urls[url] = []
            order.append(url)
        urls[url].append(row)

    url_entries = []
    for url in order:
        rows = urls[url]
        url_entries.append(
            {
                "url": url,
                "rows": rows,
                "summary": summarize(rows),
                "family_summary": build_family_summary(rows),
            }
        )

    return {
        "summary": summarize(data),
        "family_summary": build_family_summary(data),
        "urls": url_entries,
    }


def format_score(value):
    if value is None:
        return "--"
    return f"{value:.2f}%".replace(".", ",")


def format_date_long(date_obj):
    return f"{date_obj.day} {FRENCH_MONTHS[date_obj.month]} {date_obj.year}"


def strip_scheme(url):
    return re.sub(r"^https?://", "", (url or "").strip(), flags=re.I)


def safe_text(value):
    return xml_escape((value or "").replace("\r\n", "\n").replace("\r", "\n"))


def text_run(text, color="2C3E50", size=20, bold=False, italic=False, underline=False):
    properties = [
        '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial" w:eastAsia="Arial"/>',
        f'<w:color w:val="{color}"/>',
        f'<w:sz w:val="{size}"/><w:szCs w:val="{size}"/>',
    ]
    if bold:
        properties.append("<w:b/><w:bCs/>")
    if italic:
        properties.append("<w:i/><w:iCs/>")
    if underline:
        properties.append('<w:u w:val="single"/>')

    lines = (text or "").split("\n")
    runs = []
    for index, line in enumerate(lines):
        if index:
            runs.append("<w:r><w:br/></w:r>")
        runs.append(
            f'<w:r><w:rPr>{"".join(properties)}</w:rPr><w:t xml:space="preserve">{safe_text(line)}</w:t></w:r>'
        )
    return "".join(runs)


def paragraph(
    text="",
    color="2C3E50",
    size=20,
    bold=False,
    italic=False,
    underline=False,
    align="left",
    before=0,
    after=120,
    line=300,
    keep_next=False,
):
    alignment = {"left": "left", "center": "center", "right": "right"}.get(align, "left")
    keep = "<w:keepNext/>" if keep_next else ""
    return (
        f'<w:p><w:pPr>{keep}<w:spacing w:before="{before}" w:after="{after}" w:line="{line}" w:lineRule="auto"/><w:jc w:val="{alignment}"/></w:pPr>'
        f'{text_run(text, color=color, size=size, bold=bold, italic=italic, underline=underline)}</w:p>'
    )


def page_break():
    return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'


def cell(content, width, fill=None, border="D5D8DC", span=1):
    grid_span = f'<w:gridSpan w:val="{span}"/>' if span > 1 else ""
    shading = f'<w:shd w:fill="{fill}" w:val="clear"/>' if fill else ""
    return (
        "<w:tc><w:tcPr>"
        f'<w:tcW w:type="dxa" w:w="{width}"/>{grid_span}'
        "<w:tcBorders>"
        f'<w:top w:val="single" w:color="{border}" w:sz="2"/>'
        f'<w:left w:val="single" w:color="{border}" w:sz="2"/>'
        f'<w:bottom w:val="single" w:color="{border}" w:sz="2"/>'
        f'<w:right w:val="single" w:color="{border}" w:sz="2"/>'
        f"</w:tcBorders>{shading}"
        '<w:tcMar><w:top w:type="dxa" w:w="80"/><w:left w:type="dxa" w:w="120"/><w:bottom w:type="dxa" w:w="80"/><w:right w:type="dxa" w:w="120"/></w:tcMar>'
        '<w:vAlign w:val="center"/></w:tcPr>'
        f"{content}</w:tc>"
    )


def table(rows, widths, border="FFFFFF"):
    grid = "".join(f'<w:gridCol w:w="{width}"/>' for width in widths)
    body = "".join("<w:tr>" + "".join(row) + "</w:tr>" for row in rows)
    total_width = sum(widths)
    return (
        "<w:tbl><w:tblPr>"
        f'<w:tblW w:type="dxa" w:w="{total_width}"/>'
        "<w:tblBorders>"
        f'<w:top w:val="single" w:color="{border}" w:sz="2"/>'
        f'<w:left w:val="single" w:color="{border}" w:sz="2"/>'
        f'<w:bottom w:val="single" w:color="{border}" w:sz="2"/>'
        f'<w:right w:val="single" w:color="{border}" w:sz="2"/>'
        f'<w:insideH w:val="single" w:color="{border}" w:sz="2"/>'
        f'<w:insideV w:val="single" w:color="{border}" w:sz="2"/>'
        "</w:tblBorders></w:tblPr>"
        f"<w:tblGrid>{grid}</w:tblGrid>{body}</w:tbl>"
    )


def section_title(text):
    return table(
        [[cell(paragraph(text, color="2C3E50", size=28, italic=True, after=110, line=320, keep_next=True), DOC_WIDTH, fill="F8F9FA", border="D5D8DC")]],
        [DOC_WIDTH],
        border="D5D8DC",
    )


def two_column_cards(cards):
    rows = []
    for index in range(0, len(cards), 2):
        current = cards[index:index + 2]
        while len(current) < 2:
            current.append(cell("", HALF_WIDTH, border="FFFFFF"))
        rows.append(current)
    return table(rows, [HALF_WIDTH, HALF_WIDTH], border="FFFFFF")


def build_cover(summary, row, date_long):
    counts = summary["counts"]
    score = format_score(summary["score"])

    cover = []
    cover.append(
        table(
            [[cell(paragraph("RAPPORT D'AUDIT QUALITE WEB", color="FFFFFF", size=40, bold=True, after=30), DOC_WIDTH, fill="1F2D3A", border="1F2D3A")]],
            [DOC_WIDTH],
            border="1F2D3A",
        )
    )
    cover.append(paragraph("Referentiel Opquast - 245 Bonnes Pratiques", color="6C7A89", size=22, italic=True, after=30))
    cover.append(paragraph(f'{row["audit"]} - {row["url"]}', color="2C3E50", size=22, bold=True, after=160))

    cover.append(
        table(
            [[
                cell(paragraph(score, color="FFFFFF", size=64, bold=True, align="center", before=140, after=140), 3000, fill="2BBDAA", border="FFFFFF"),
                cell(
                    paragraph("Score de conformite", color="163A69", size=30, bold=True, after=30)
                    + paragraph(f'{counts["Conforme"]} conformes - {counts["Non conforme"]} non conformes', color="3B5C82", size=18, italic=True, after=20)
                    + paragraph(f'{counts["A verifier"]} a verifier - {counts["Non applicable"]} non applicables', color="163A69", size=18, after=20),
                    DOC_WIDTH - 3000,
                    fill="D9F5EE",
                    border="FFFFFF",
                ),
            ]],
            [3000, DOC_WIDTH - 3000],
        )
    )
    cover.append(paragraph("", after=160))

    tiles = []
    for status in STATUSES:
        value_color = {"Conforme": "1E8449", "Non conforme": "C0392B", "A verifier": "AF601A", "Non applicable": "5D6D7E"}[status]
        title_fill = {"Conforme": "2ECC71", "Non conforme": "E74C3C", "A verifier": "F39C12", "Non applicable": "95A5A6"}[status]
        tile = cell(
            table(
                [
                    [cell(paragraph(status, color="FFFFFF", size=18, bold=True, align="center", before=20, after=20), HALF_WIDTH - 240, fill=title_fill, border=title_fill)],
                    [cell(paragraph(str(counts[status]), color=value_color, size=44, bold=True, align="center", before=120, after=10) + paragraph(f'{(counts[status] / summary["total"] * 100 if summary["total"] else 0):.1f}%'.replace(".", ","), color=value_color, size=18, italic=True, align="center", after=40), HALF_WIDTH - 240, fill=STATUS_SOFT[status], border="FFFFFF")],
                ],
                [HALF_WIDTH - 240],
                border="FFFFFF",
            ),
            HALF_WIDTH,
            border="FFFFFF",
        )
        tiles.append(tile)
    cover.append(two_column_cards(tiles))
    cover.append(paragraph("", after=160))
    cover.append(paragraph("URL auditee", color="163A69", size=22, bold=True, after=20))
    cover.append(paragraph(row["url"], color="163A69", size=20, underline=True, after=30))
    cover.append(paragraph(f"Date de l'audit : {date_long}", color="6C7A89", size=18, italic=True, after=40))
    return "".join(cover)


def build_toc():
    items = [
        "1. Contexte et objectifs de l'audit",
        "2. Methodologie",
        "3. Synthese des resultats",
        "4. Resultats detailles par famille",
        "5. Non-conformites et recommandations",
        "6. Plan d'action priorise",
        "7. Recommandations generales",
        "8. Annexe - Liste complete des regles",
    ]
    parts = [section_title("Sommaire")]
    for item in items:
        parts.append(paragraph(item, color="2C3E50", size=22, after=90))
        parts.append(table([[cell("", DOC_WIDTH, border="E6EBEF")]], [DOC_WIDTH], border="E6EBEF"))
    return "".join(parts)


def build_context_section():
    objectives = [
        ("Evaluation objective", "Mesurer le niveau de qualite actuel du site selon un referentiel reconnu et standardise."),
        ("Identification des points faibles", "Recenser les regles non respectees et hierarchiser les problemes selon leur impact sur l'utilisateur."),
        ("Base de travail", "Fournir aux equipes techniques et editoriales une liste concrete d'actions correctives a mettre en oeuvre."),
        ("Suivi dans le temps", "Etablir un score de reference permettant de mesurer les progres lors des audits suivants."),
    ]

    cards = []
    for title, description in objectives:
        cards.append(
            cell(
                paragraph(title, color="163A69", size=20, bold=True, after=30)
                + paragraph(description, color="2C3E50", size=18, after=30),
                HALF_WIDTH,
                border="D5D8DC",
            )
        )

    return "".join(
        [
            section_title("1. Contexte et objectifs de l'audit"),
            paragraph("Qu'est-ce qu'Opquast ?", color="163A69", size=32, bold=True, after=60, line=320, keep_next=True),
            paragraph(
                "Opquast (Open Quality Standards) est un referentiel de bonnes pratiques pour la qualite web, developpe pour structurer l'evaluation de la qualite numerique. Il rassemble 245 regles verifiables, organisees en familles thematiques et centrees sur l'experience utilisateur finale.",
                size=18,
                after=110,
            ),
            paragraph(
                "Contrairement aux referentiels purement techniques, Opquast adopte une approche transversale et couvre accessibilite, contenus, performance, securite, conformite et ergonomie.",
                size=18,
                after=160,
            ),
            paragraph("Objectifs de cet audit", color="163A69", size=30, bold=True, after=70, line=320, keep_next=True),
            two_column_cards(cards),
        ]
    )


def build_methodology_section(row, summary, date_short):
    perimeter = table(
        [[
            cell(
                paragraph("Site audite", bold=True, size=18, after=10)
                + paragraph(strip_scheme(row["url"]), size=18, after=40)
                + paragraph("Type de cible", bold=True, size=18, after=10)
                + paragraph(row["type"], size=18, after=40)
                + paragraph("Date de l'audit", bold=True, size=18, after=10)
                + paragraph(date_short, size=18, after=40)
                + paragraph("Nombre de regles", bold=True, size=18, after=10)
                + paragraph(str(summary["total"]), size=18, after=10),
                HALF_WIDTH,
                border="D5D8DC",
            ),
            cell(
                paragraph("URL cible", bold=True, size=18, after=10)
                + paragraph(row["url"], size=18, after=40)
                + paragraph("Referentiel", bold=True, size=18, after=10)
                + paragraph("Opquast - Qualite Numerique (245 regles)", size=18, after=40)
                + paragraph("Statut", bold=True, size=18, after=10)
                + paragraph(row["statut_audit"], size=18, after=10),
                HALF_WIDTH,
                border="D5D8DC",
            ),
        ]],
        [HALF_WIDTH, HALF_WIDTH],
        border="D5D8DC",
    )

    status_cards = []
    descriptions = {
        "Conforme": "La regle est respectee sur la page ou le site audite.",
        "Non conforme": "La regle n'est pas respectee. Une action corrective est necessaire.",
        "A verifier": "La regle n'a pas encore ete verifiee ou necessite une investigation complementaire.",
        "Non applicable": "La regle ne s'applique pas au contexte de la page auditee.",
    }
    title_colors = {
        "Conforme": "1E8449",
        "Non conforme": "C0392B",
        "A verifier": "AF601A",
        "Non applicable": "5D6D7E",
    }
    for status in STATUSES:
        status_cards.append(
            cell(
                paragraph(status, color=title_colors[status], size=22, bold=True, after=20)
                + paragraph(descriptions[status], size=18, after=20),
                HALF_WIDTH,
                fill=STATUS_SOFT[status],
                border="D5D8DC",
            )
        )

    return "".join(
        [
            section_title("2. Methodologie"),
            paragraph("Perimetre de l'audit", color="163A69", size=30, bold=True, after=70, line=320, keep_next=True),
            perimeter,
            paragraph("Les 4 statuts possibles", color="163A69", size=30, bold=True, after=70, line=320, keep_next=True),
            two_column_cards(status_cards),
        ]
    )


def build_synthesis_section(summary):
    counts = summary["counts"]
    score = format_score(summary["score"])

    tiles = []
    for status in STATUSES:
        tiles.append(
            cell(
                paragraph(str(counts[status]), color="163A69", size=42, bold=True, align="center", before=100, after=10)
                + paragraph(status, color="163A69", size=18, align="center", after=50),
                HALF_WIDTH,
                fill=STATUS_SOFT[status],
                border="D5D8DC",
            )
        )

    graph_rows = []
    for status in STATUSES:
        pct = (counts[status] / summary["total"] * 100) if summary["total"] else 0.0
        bar_color = {"Conforme": "2ECC71", "Non conforme": "E74C3C", "A verifier": "F39C12", "Non applicable": "95A5A6"}[status]
        filled = max(1, int((pct / 100.0) * 5200)) if counts[status] else 0
        filled = min(filled, 5200)
        graph_rows.append(
            [
                cell(paragraph(status, size=18, bold=True), 1700, border="FFFFFF"),
                cell("", 5200, fill="ECF0F1", border="FFFFFF"),
                cell("", filled or 1, fill=bar_color if counts[status] else "ECF0F1", border=bar_color if counts[status] else "ECF0F1"),
                cell(paragraph(f'{counts[status]} ({pct:.1f}%)'.replace(".", ","), size=18), 1738, border="FFFFFF"),
            ]
        )

    return "".join(
        [
            section_title("3. Synthese des resultats"),
            table(
                [[cell(paragraph(score, align="center", color="163A69", size=38, bold=True, before=70, after=10) + paragraph("Score global", align="center", color="163A69", size=18, after=40), DOC_WIDTH, fill="D9F5EE", border="FFFFFF")]],
                [DOC_WIDTH],
            ),
            paragraph("", after=80),
            two_column_cards(tiles),
            paragraph("Graphiques", color="163A69", size=30, bold=True, after=60, line=320, keep_next=True),
            table(
                [
                    [cell(paragraph("Repartition des statuts", color="163A69", size=22, bold=True, after=30), DOC_WIDTH, border="D5D8DC", span=4)],
                    *graph_rows,
                ],
                [1700, 5200, 1, 1738],
                border="FFFFFF",
            ),
        ]
    )


def build_family_section(family_summary):
    widths = [3000, 1100, 1100, 1100, 1100, 1100, 1128]
    rows = [[
        cell(paragraph("Famille", align="center", bold=True, size=16), widths[0], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("Conf.", align="center", bold=True, size=16), widths[1], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("Non conf.", align="center", bold=True, size=16), widths[2], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("A ver.", align="center", bold=True, size=16), widths[3], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("N/A", align="center", bold=True, size=16), widths[4], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("Total", align="center", bold=True, size=16), widths[5], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("Score", align="center", bold=True, size=16), widths[6], fill="EEF4F8", border="D5D8DC"),
    ]]

    total_conf = total_non_conf = total_a_ver = total_na = total = 0
    for family in sorted(family_summary.keys()):
        values = family_summary[family]
        total_conf += values["Conforme"]
        total_non_conf += values["Non conforme"]
        total_a_ver += values["A verifier"]
        total_na += values["Non applicable"]
        total += values["total"]
        rows.append(
            [
                cell(paragraph(family, size=16), widths[0], border="D5D8DC"),
                cell(paragraph(str(values["Conforme"]), align="center", size=16), widths[1], border="D5D8DC"),
                cell(paragraph(str(values["Non conforme"]), align="center", size=16), widths[2], border="D5D8DC"),
                cell(paragraph(str(values["A verifier"]), align="center", size=16), widths[3], border="D5D8DC"),
                cell(paragraph(str(values["Non applicable"]), align="center", size=16), widths[4], border="D5D8DC"),
                cell(paragraph(str(values["total"]), align="center", size=16), widths[5], border="D5D8DC"),
                cell(paragraph(format_score(values["score"]), align="center", size=16), widths[6], border="D5D8DC"),
            ]
        )

    total_score = round((total_conf / (total_conf + total_non_conf)) * 100, 2) if (total_conf + total_non_conf) else None
    rows.append(
        [
            cell(paragraph("TOTAL", bold=True, size=16), widths[0], border="D5D8DC"),
            cell(paragraph(str(total_conf), align="center", bold=True, size=16), widths[1], border="D5D8DC"),
            cell(paragraph(str(total_non_conf), align="center", bold=True, size=16), widths[2], border="D5D8DC"),
            cell(paragraph(str(total_a_ver), align="center", bold=True, size=16), widths[3], border="D5D8DC"),
            cell(paragraph(str(total_na), align="center", bold=True, size=16), widths[4], border="D5D8DC"),
            cell(paragraph(str(total), align="center", bold=True, size=16), widths[5], border="D5D8DC"),
            cell(paragraph(format_score(total_score), align="center", bold=True, size=16), widths[6], border="D5D8DC"),
        ]
    )

    return "".join(
        [
            section_title("4. Resultats detailles par famille"),
            paragraph(
                "Le tableau ci-dessous presente pour chaque famille de regles le nombre de regles dans chaque statut, ainsi que le score de conformite calcule sur les regles evaluees.",
                size=18,
                after=80,
            ),
            table(rows, widths, border="D5D8DC"),
            paragraph("Score >= 80% : Excellent", size=18, after=20),
            paragraph("Score 50-79% : A ameliorer", size=18, after=20),
            paragraph("Score < 50% : Prioritaire", size=18, after=20),
            paragraph("N/A : Aucune regle evaluee", size=18, after=20),
        ]
    )


def build_non_conformities_section(data):
    non_conf = [row for row in data if row["statut"] == "Non conforme"]
    parts = [section_title("5. Non-conformites et recommandations")]
    if not non_conf:
        parts.append(paragraph("Aucune non-conformite detectee.", size=18, after=40))
        return "".join(parts)

    for row in non_conf:
        parts.append(
            table(
                [[
                    cell(
                        paragraph(f'{row["num"]}. {row["intitule"]}', color="C0392B", size=20, bold=True, after=20)
                        + paragraph(f'Famille : {row["famille"]}', size=18, bold=True, after=15)
                        + paragraph(
                            f'Constat : {row["commentaire"] or "La regle n est pas respectee sur la cible auditee."}',
                            size=18,
                            after=15,
                        )
                        + paragraph(
                            f'Proposition de correction : {row["preuve"] or row["commentaire"] or "Analyser la regle, corriger le point de blocage puis recontroler la cible."}',
                            size=18,
                            after=15,
                        )
                        + (paragraph(f'Source : {row["source"]}', size=18, after=10) if row["source"] else ""),
                        DOC_WIDTH,
                        fill="FDEDEC",
                        border="F5B7B1",
                    )
                ]],
                [DOC_WIDTH],
                border="F5B7B1",
            )
        )
        parts.append(paragraph("", after=50))
    return "".join(parts)


def build_action_plan_section(data):
    actionable = [row for row in data if row["statut"] in ("Non conforme", "A verifier")]
    actionable.sort(key=lambda row: (row["statut"] != "Non conforme", row["famille"], row["num"]))

    parts = [section_title("6. Plan d'action priorise")]
    if not actionable:
        parts.append(paragraph("Aucune action prioritaire n'a ete identifiee.", size=18))
        return "".join(parts)

    widths = [900, 3000, 1200, 4538]
    rows = [[
        cell(paragraph("Regle", align="center", bold=True, size=16), widths[0], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("Famille", align="center", bold=True, size=16), widths[1], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("Statut", align="center", bold=True, size=16), widths[2], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("Action recommandee", align="center", bold=True, size=16), widths[3], fill="EEF4F8", border="D5D8DC"),
    ]]
    for row in actionable[:20]:
        action_text = row["preuve"] or row["commentaire"] or "Documenter le constat, corriger puis recontroler la regle."
        rows.append(
            [
                cell(paragraph(row["num"], align="center", size=16), widths[0], border="D5D8DC"),
                cell(paragraph(row["famille"], size=16), widths[1], border="D5D8DC"),
                cell(paragraph(row["statut"], align="center", size=16), widths[2], border="D5D8DC"),
                cell(paragraph(action_text, size=16), widths[3], border="D5D8DC"),
            ]
        )
    parts.append(table(rows, widths, border="D5D8DC"))
    return "".join(parts)


def build_recommendations_section(summary):
    counts = summary["counts"]
    recommendations = [
        "Finaliser en priorite le traitement des non-conformites pour relever rapidement le score de conformite.",
        "Documenter systematiquement les preuves et commentaires sur les regles sensibles afin de faciliter les arbitrages.",
        "Planifier la verification progressive des regles encore en attente pour fiabiliser le score global.",
        "Rejouer cet audit apres corrections pour mesurer objectivement l'evolution de la qualite numerique.",
    ]
    parts = [section_title("7. Recommandations generales")]
    parts.append(
        paragraph(
            f'Cet audit comporte {counts["Non conforme"]} non-conformites et {counts["A verifier"]} regles encore a verifier. Les recommandations suivantes visent a transformer ce constat en plan d amelioration concret.',
            size=18,
            after=110,
        )
    )
    for rec in recommendations:
        parts.append(paragraph(f"- {rec}", size=18, after=45))
    return "".join(parts)


def build_annex_section(data):
    widths = [700, 7138, 1800]
    parts = [section_title("8. Annexe - Liste complete des regles")]
    for family in sorted({row["famille"] for row in data}):
        family_rows = [row for row in data if row["famille"] == family]
        parts.append(paragraph(family, color="163A69", size=22, bold=True, after=30))
        rows = [[
            cell(paragraph("No", align="center", bold=True, size=16), widths[0], fill="EEF4F8", border="D5D8DC"),
            cell(paragraph("Intitule de la regle", bold=True, size=16), widths[1], fill="EEF4F8", border="D5D8DC"),
            cell(paragraph("Statut", align="center", bold=True, size=16), widths[2], fill="EEF4F8", border="D5D8DC"),
        ]]
        for row in family_rows:
            rows.append(
                [
                    cell(paragraph(row["num"], align="center", size=16), widths[0], border="D5D8DC"),
                    cell(paragraph(row["intitule"], size=16), widths[1], border="D5D8DC"),
                    cell(paragraph(row["statut"], align="center", size=16), widths[2], border="D5D8DC"),
                ]
            )
        parts.append(table(rows, widths, border="D5D8DC"))
        parts.append(paragraph("", after=40))
    return "".join(parts)


def extract_section_properties(template_document_xml):
    match = re.search(rb"(<w:sectPr[\s\S]*?</w:sectPr>)", template_document_xml)
    if match:
        return match.group(1).decode("utf-8")
    return (
        '<w:sectPr>'
        '<w:headerReference w:type="default" r:id="rId6"/>'
        '<w:footerReference w:type="default" r:id="rId7"/>'
        '<w:pgSz w:w="11906" w:h="16838"/>'
        '<w:pgMar w:top="1134" w:right="1134" w:bottom="1534" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/>'
        "</w:sectPr>"
    )


def build_document_xml(template_document_xml, body_xml):
    section_properties = extract_section_properties(template_document_xml)
    return (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas" '
        'xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" '
        'xmlns:o="urn:schemas-microsoft-com:office:office" '
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
        'xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" '
        'xmlns:v="urn:schemas-microsoft-com:vml" '
        'xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing" '
        'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
        'xmlns:w10="urn:schemas-microsoft-com:office:word" '
        'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        'xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml" '
        'xmlns:w15="http://schemas.microsoft.com/office/word/2012/wordml" '
        'xmlns:wpg="http://schemas.microsoft.com/office/word/2010/wordprocessingGroup" '
        'xmlns:wpi="http://schemas.microsoft.com/office/word/2010/wordprocessingInk" '
        'xmlns:wne="http://schemas.microsoft.com/office/word/2006/wordml" '
        'xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape" '
        'mc:Ignorable="w14 w15 wp14"><w:body>'
        + body_xml
        + section_properties
        + "</w:body></w:document>"
    ).encode("utf-8")


def update_header(header_xml, row):
    text = header_xml.decode("utf-8")
    if "https://newsplugins.test" in text:
        text = text.replace("https://newsplugins.test", safe_text(row["url"]))
    return text.encode("utf-8")


def update_footer(footer_xml, row, date_long):
    text = footer_xml.decode("utf-8")
    text = re.sub(
        r"Test audit\s*[·\-\u00b7]\s*21 mars 2026",
        safe_text(f'{row["audit"]} · {date_long}'),
        text,
        count=1,
    )
    return text.encode("utf-8")


def update_core(core_xml, row, date_iso):
    text = core_xml.decode("utf-8")
    title = safe_text(row["audit"] or "Rapport Audit Opquast")
    subject = safe_text(f'Audit Opquast URL - {row["url"]}')
    description = safe_text(f'Restitution DOCX de l audit Opquast pour {row["url"]}')
    text = re.sub(r"<dc:title>.*?</dc:title>", f"<dc:title>{title}</dc:title>", text, count=1)
    text = re.sub(r"<dc:subject>.*?</dc:subject>", f"<dc:subject>{subject}</dc:subject>", text, count=1)
    text = re.sub(r"<dc:description>.*?</dc:description>", f"<dc:description>{description}</dc:description>", text, count=1)
    text = re.sub(r"<dcterms:modified [^>]+>.*?</dcterms:modified>", f'<dcterms:modified xsi:type="dcterms:W3CDTF">{date_iso}</dcterms:modified>', text, count=1)
    return text.encode("utf-8")


def build_body(context):
    row = context["row"]
    summary = context["summary"]

    parts = [
        build_cover(summary, row, context["date_long"]),
        page_break(),
        build_toc(),
        page_break(),
        build_context_section(),
        page_break(),
        build_methodology_section(row, summary, context["date_short"]),
        page_break(),
        build_synthesis_section(summary),
        page_break(),
        build_family_section(context["family_summary"]),
        page_break(),
        build_non_conformities_section(context["data"]),
        page_break(),
        build_action_plan_section(context["data"]),
        page_break(),
        build_recommendations_section(summary),
        page_break(),
        build_annex_section(context["data"]),
    ]
    return "".join(parts)


def build_site_body(context):
    row = context["row"]
    site = context["site"]
    summary = site["summary"]
    urls = site["urls"]

    widths = [2200, 1400, 1400, 1400, 1400, 1798]
    compare_rows = [[
        cell(paragraph("URL", align="center", bold=True, size=16), widths[0], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("Conforme", align="center", bold=True, size=16), widths[1], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("Non conforme", align="center", bold=True, size=16), widths[2], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("A verifier", align="center", bold=True, size=16), widths[3], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("N/A", align="center", bold=True, size=16), widths[4], fill="EEF4F8", border="D5D8DC"),
        cell(paragraph("Score", align="center", bold=True, size=16), widths[5], fill="EEF4F8", border="D5D8DC"),
    ]]

    for entry in urls:
        counts = entry["summary"]["counts"]
        compare_rows.append([
            cell(paragraph(strip_scheme(entry["url"]), size=16), widths[0], border="D5D8DC"),
            cell(paragraph(str(counts["Conforme"]), align="center", size=16), widths[1], border="D5D8DC"),
            cell(paragraph(str(counts["Non conforme"]), align="center", size=16), widths[2], border="D5D8DC"),
            cell(paragraph(str(counts["A verifier"]), align="center", size=16), widths[3], border="D5D8DC"),
            cell(paragraph(str(counts["Non applicable"]), align="center", size=16), widths[4], border="D5D8DC"),
            cell(paragraph(format_score(entry["summary"]["score"]), align="center", size=16), widths[5], border="D5D8DC"),
        ])

    parts = [
        build_cover(summary, row, context["date_long"]),
        page_break(),
        build_toc(),
        page_break(),
        section_title("1. Contexte et objectifs de l'audit"),
        paragraph(
            "Cet audit de type Site consolide plusieurs URLs auditees individuellement. Chaque URL conserve ses 245 regles, ses propres statuts et ses propres preuves.",
            size=18,
            after=100,
        ),
        paragraph(
            f"{len(urls)} URL(s) auditees sont prises en compte dans cette restitution.",
            size=18,
            after=120,
        ),
        page_break(),
        section_title("2. Methodologie"),
        paragraph(
            "Le detail des regles reste evalue page par page. Le score global du site est calcule sur l'ensemble des URLs auditees selon la formule : conformes / (conformes + non conformes).",
            size=18,
            after=100,
        ),
        build_methodology_section(row, summary, context["date_short"]),
        page_break(),
        section_title("3. Synthese des resultats"),
        build_synthesis_section(summary),
        page_break(),
        section_title("4. Comparatif des URLs"),
        paragraph(
            "Le tableau ci-dessous compare les statuts et le score de conformite de chaque URL auditée.",
            size=18,
            after=70,
        ),
        table(compare_rows, widths, border="D5D8DC"),
        page_break(),
        section_title("5. Non-conformites et recommandations"),
        build_non_conformities_section(context["data"]),
        page_break(),
        section_title("6. Plan d'action priorise"),
        build_action_plan_section(context["data"]),
        page_break(),
        section_title("7. Recommandations generales"),
        build_recommendations_section(summary),
        page_break(),
        section_title("8. Annexe - Detail par URL"),
    ]

    for entry in urls:
        parts.append(paragraph(entry["url"], color="163A69", size=24, bold=True, after=40, line=320, keep_next=True))
        parts.append(paragraph(
            f"Score : {format_score(entry['summary']['score'])} - Regles traitees : {entry['summary']['treated']} / {entry['summary']['total']}",
            size=18,
            after=40,
        ))
        parts.append(build_annex_section(entry["rows"]))
        parts.append(paragraph("", after=80))

    return "".join(parts)


def read_docx_entries(path):
    with zipfile.ZipFile(path, "r") as archive:
        return {entry.filename: archive.read(entry.filename) for entry in archive.infolist()}


def write_docx(output_path, context):
    entries = read_docx_entries(TEMPLATE_DOCX)
    entries["word/document.xml"] = build_document_xml(entries["word/document.xml"], build_body(context))
    if "word/header1.xml" in entries:
        entries["word/header1.xml"] = update_header(entries["word/header1.xml"], context["row"])
    if "word/footer1.xml" in entries:
        entries["word/footer1.xml"] = update_footer(entries["word/footer1.xml"], context["row"], context["date_long"])
    if "docProps/core.xml" in entries:
        entries["docProps/core.xml"] = update_core(entries["docProps/core.xml"], context["row"], context["date_iso"])
    entries.pop("word/afchunk.html", None)

    with zipfile.ZipFile(output_path, "w", compression=zipfile.ZIP_DEFLATED) as archive:
        for name, content in entries.items():
            archive.writestr(name, content)


def main(csv_path, docx_path):
    if not os.path.isfile(TEMPLATE_DOCX):
        print(f"[ERREUR] Template DOCX introuvable : {TEMPLATE_DOCX}")
        return 1
    if not os.path.isfile(csv_path):
        print(f"[ERREUR] Fichier CSV introuvable : {csv_path}")
        return 1

    rows = load_csv(csv_path)
    if not rows:
        print("[ERREUR] CSV vide ou illisible.")
        return 1

    data = parse_rows(rows)
    now = datetime.now()
    site = build_site_context(data) if data and (data[0].get("type", "").lower() == "site") else None
    context = {
        "data": data,
        "row": data[0],
        "summary": summarize(data),
        "family_summary": build_family_summary(data),
        "site": site,
        "date_long": format_date_long(now),
        "date_short": now.strftime("%d/%m/%Y"),
        "date_iso": now.strftime("%Y-%m-%dT%H:%M:%SZ"),
    }

    if site:
        entries = read_docx_entries(TEMPLATE_DOCX)
        entries["word/document.xml"] = build_document_xml(entries["word/document.xml"], build_site_body(context))
        if "word/header1.xml" in entries:
            entries["word/header1.xml"] = update_header(entries["word/header1.xml"], context["row"])
        if "word/footer1.xml" in entries:
            entries["word/footer1.xml"] = update_footer(entries["word/footer1.xml"], context["row"], context["date_long"])
        if "docProps/core.xml" in entries:
            entries["docProps/core.xml"] = update_core(entries["docProps/core.xml"], context["row"], context["date_iso"])
        entries.pop("word/afchunk.html", None)
        with zipfile.ZipFile(docx_path, "w", compression=zipfile.ZIP_DEFLATED) as archive:
            for name, content in entries.items():
                archive.writestr(name, content)
    else:
        write_docx(docx_path, context)

    print(f"[Opquast DOCX] DOCX genere : {docx_path}")
    return 0


if __name__ == "__main__":
    csv_input = sys.argv[1] if len(sys.argv) > 1 else DEFAULT_CSV
    docx_output = sys.argv[2] if len(sys.argv) > 2 else DEFAULT_DOCX
    sys.exit(main(csv_input, docx_output))
