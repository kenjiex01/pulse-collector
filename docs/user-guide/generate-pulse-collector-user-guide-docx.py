#!/usr/bin/env python3
"""User guide for Pulse Biometric Collector (campus staff, non-technical)."""

from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor

ROOT = Path(__file__).resolve().parent
IMG = ROOT / "collector-images"
OUT = ROOT / "Pulse-Biometric-Collector-User-Guide.docx"


def set_run_font(run, *, size=11, bold=False, italic=False, color=None):
    run.font.name = "Calibri"
    run._element.rPr.rFonts.set(qn("w:eastAsia"), "Calibri")
    run.font.size = Pt(size)
    run.bold = bold
    run.italic = italic
    if color is not None:
        run.font.color.rgb = color


def add_heading(doc, text, level=1):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(16 if level == 1 else 12)
    p.paragraph_format.space_after = Pt(6)
    run = p.add_run(text)
    if level == 1:
        set_run_font(run, size=20, bold=True, color=RGBColor(0x1F, 0x4E, 0x79))
    elif level == 2:
        set_run_font(run, size=14, bold=True, color=RGBColor(0x2E, 0x75, 0xB6))
    else:
        set_run_font(run, size=12, bold=True, color=RGBColor(0x1E, 0x3A, 0x5F))
    return p


def add_para(doc, text):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(8)
    p.paragraph_format.line_spacing = 1.15
    run = p.add_run(text)
    set_run_font(run, size=11)
    return p


def add_bullet(doc, text):
    p = doc.add_paragraph(style="List Bullet")
    p.paragraph_format.space_after = Pt(3)
    if p.runs:
        p.runs[0].text = text
        set_run_font(p.runs[0], size=11)
    else:
        run = p.add_run(text)
        set_run_font(run, size=11)
    return p


def add_caption(doc, text):
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after = Pt(12)
    run = p.add_run(text)
    set_run_font(run, size=9, italic=True, color=RGBColor(0x64, 0x74, 0x8B))
    return p


def add_shot(doc, filename, caption):
    path = IMG / filename
    if not path.exists():
        add_para(doc, f"(Missing screenshot: {filename})")
        return
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run()
    run.add_picture(str(path), width=Inches(6.4))
    add_caption(doc, caption)


def main():
    doc = Document()
    section = doc.sections[0]
    section.top_margin = Inches(0.75)
    section.bottom_margin = Inches(0.75)
    section.left_margin = Inches(0.85)
    section.right_margin = Inches(0.85)

    add_heading(doc, "Pulse Biometric Collector — User Guide", 1)
    add_para(doc, "For campus staff. Version 1.0.3. No technical background required.")
    add_para(
        doc,
        "Pulse (Biometric Collector) is the desktop app on the campus PC. It reads time-in and time-out "
        "from ZKTeco biometric devices and sends them to the cloud so People360 can use them later. "
        "Pulse is not the payroll app — People360 handles pay and attendance processing.",
    )

    add_heading(doc, "1. Before you start", 2)
    add_bullet(doc, "Pulse is installed on the campus computer (Windows or Mac).")
    add_bullet(doc, "Biometric devices are powered on and on the same network as the PC (Wi‑Fi or LAN).")
    add_bullet(doc, "Keep Pulse open while the campus is operating — automatic collection only runs while the app is open.")
    add_bullet(doc, "Know each device’s name, IP address (e.g. 192.168.1.201), and Comm key (usually 0 if no password is set).")

    add_heading(doc, "2. Opening the app", 2)
    add_para(
        doc,
        "Double-click the Pulse icon. The Dashboard opens. At the top you will see: "
        "Dashboard, Collected logs, Download DTR, Deleted log backups, Upload backup JSON, and Device users. "
        "The version (e.g. v1.0.3) appears in the top-right corner. If an update is offered, wait for it to finish before continuing.",
    )

    add_heading(doc, "3. Dashboard — first-time setup", 2)
    add_para(doc, "This is your daily home screen. Work through the cards from top to bottom.")
    add_shot(doc, "dashboard.png", "Figure 1. Dashboard — name, start date, months to keep, and Collect now.")

    add_heading(doc, "Collector name", 3)
    add_para(
        doc,
        "Enter a label for this campus or desk (e.g. Cainta Campus — Front Desk). Click Save name. "
        "This name is used when logs are sent to the cloud so People360 can tell which campus they came from.",
    )

    add_heading(doc, "Attendance start date", 3)
    add_para(
        doc,
        "Optional. If you set a date, punches older than that date will not be collected on the next run. "
        "If left blank, Pulse collects all punches the device returns. Click Save start date after choosing a date.",
    )

    add_heading(doc, "Months to keep (auto-delete on this PC and on the time clock)", 3)
    add_para(
        doc,
        "The default is 2 months. After Collect now or an automatic collect, punches older than that are saved to a JSON backup first, "
        "then removed from Pulse on this PC. The time clock is cleared only at the end of the month (or on the next collect if that day was missed), "
        "and only when it still has a punch older than the cutoff. Pulse saves a JSON backup and checks that those old punches are in the file before it erases anything on the clock. "
        "The time clock can only clear its whole attendance log, not one date at a time, so newer punches on the clock are included in that same backup and stay in Pulse. "
        "Users and fingerprints on the device are not removed. If the backup cannot be saved, the clock is not cleared. Find the files under Deleted log backups.",
    )
    add_bullet(doc, "Click Save retention after changing the number.")
    add_bullet(doc, "Do not leave Months to keep blank — collection will not run.")
    add_bullet(doc, "Pulse will not erase the time clock if the JSON backup file cannot be saved.")

    add_heading(doc, "Collect now", 3)
    add_para(
        doc,
        "While the app is open, Pulse collects logs automatically (usually every 5 minutes). "
        "Click Collect now (manual) for an immediate run. You can keep using the screen while collection runs. "
        "When a device is reachable, Connectivity shows Online.",
    )

    add_heading(doc, "4. Biometric devices", 2)
    add_para(
        doc,
        "The device table is at the bottom of the Dashboard. Device storage (attendance) shows how many log slots "
        "are used and how many are free, e.g. 1,500 / 80,000 (78,500 free). These are attendance record counts on the device, not disk space in GB. "
        "Numbers appear when the device is Online. While Offline or during collect, you may see a dash (—).",
    )
    add_bullet(doc, "Add device — register a new time clock.")
    add_bullet(doc, "Edit — change name, IP, port, or Comm key.")
    add_bullet(doc, "Users — people enrolled on that device.")
    add_bullet(doc, "Logs — punches already collected from that device.")
    add_bullet(doc, "Remove — remove the device from Pulse (does not delete the physical device).")

    add_heading(doc, "Adding a device", 3)
    add_shot(doc, "add-device.png", "Figure 2. Add device — name, model, IP, port, and Comm key.")
    add_para(doc, "On the Dashboard, click Add device, then fill in:")
    add_bullet(doc, "Device name — a friendly label, e.g. Main Gate.")
    add_bullet(doc, "Model — usually K30; leave as shown if correct.")
    add_bullet(doc, "IP address — the device’s network address. Ask IT if unsure.")
    add_bullet(doc, "Port — usually 4370. Do not change unless IT says so.")
    add_bullet(doc, "Comm key — device password. Use 0 if none is configured.")
    add_para(doc, "Click Save device. Return to the Dashboard and wait for Online. If status is Offline or port closed, check IP, power, and TCP/comm settings on the device.")

    add_heading(doc, "Connectivity: Online vs port closed", 3)
    add_para(
        doc,
        "Online means Pulse can connect to the device on TCP port 4370 (required to collect). "
        "Ping alone is not enough — the device must accept connections on that port. "
        "If ping works but Pulse shows port closed, enable Ethernet/TCP comm on the ZKTeco and confirm port 4370 on the device menu.",
    )

    add_heading(doc, "5. Collected logs", 2)
    add_para(
        doc,
        "View time-in and time-out records stored on the Pulse PC. "
        "Filter by device, date, or name / ID. "
        "Pushed or uploaded means the log was sent to the cloud. "
        "People360 still needs to pull biometric logs in Time Logs before they appear in payroll.",
    )
    add_shot(doc, "logs.png", "Figure 3. Collected logs — find punches by device and date.")

    add_heading(doc, "6. Download DTR", 2)
    add_para(
        doc,
        "Use this when you need a Daily Time Record file (spreadsheet-style) for selected people and dates. "
        "Choose device, date range, and users, then download. "
        "This is a report from collected logs — it does not replace payroll in People360.",
    )
    add_shot(doc, "dtr.png", "Figure 4. Download DTR — select device, dates, and users.")

    add_heading(doc, "7. Deleted log backups", 2)
    add_para(
        doc,
        "When logs are older than Months to keep, Pulse archives them to a backup file before removing them from the list. "
        "Deleted log backups lets you view and download those files. "
        "Upload backup JSON restores an archive if you need old punches back in Pulse.",
    )
    add_shot(doc, "archives.png", "Figure 5. Deleted log backups — list of archived old logs.")
    add_shot(doc, "upload-backup.png", "Figure 6. Upload backup JSON — restore archived logs when needed.")

    add_heading(doc, "8. Device users", 2)
    add_para(
        doc,
        "People enrolled on the biometric device (name, user ID, fingerprint). "
        "You can refresh from the device, add users, or enroll fingerprints while the person is at the machine. "
        "Follow the on-screen steps when enrolling (usually several finger scans).",
    )
    add_shot(doc, "users.png", "Figure 7. Device users — enrolled users on the time clock.")

    add_heading(doc, "9. Daily checklist", 2)
    add_bullet(doc, "Open Pulse in the morning and leave it running.")
    add_bullet(doc, "On the Dashboard, confirm each device shows Online (or fix port closed / offline).")
    add_bullet(doc, "If a device is offline, check power, network, and IP; try Collect now afterward.")
    add_bullet(doc, "Check Device storage. If free space is low, notify IT — Pulse does not auto-clear logs on the device.")
    add_bullet(doc, "In People360, pull biometric logs when attendance or payroll needs them.")

    add_heading(doc, "10. Common questions", 2)
    add_para(doc, "Why are there no new punches?")
    add_para(
        doc,
        "Make sure Pulse is open, the device is Online, and Months to keep is saved. "
        "Click Collect now and check Collected logs for new rows.",
    )
    add_para(doc, "Does Pulse delete logs on the biometric machine?")
    add_para(
        doc,
        "Yes, at the end of the month, and only after a JSON backup. If the clock still has a punch older than Months to keep, "
        "Pulse saves those punches to a JSON file, checks the file, then clears the attendance log on the device. "
        "If that last day was missed, the next collect does it once. Punches still inside the retention window stay in Pulse. "
        "Users and fingerprints are not deleted. If the backup cannot be saved, the clock is not cleared.",
    )
    add_para(doc, "Why are punches not in People360 yet?")
    add_para(
        doc,
        "Pulse sends logs to the cloud. In People360 you must pull biometric logs under Time Logs before they are used in payroll.",
    )

    add_para(doc, "App: Pulse Biometric Collector.")

    doc.save(OUT)
    print(f"Wrote {OUT} ({OUT.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
