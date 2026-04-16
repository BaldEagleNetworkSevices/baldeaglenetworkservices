from __future__ import annotations

import hashlib
import json
import logging
from typing import Any

import requests

from config import CRMConfig

CRM_META_START = "\n\n[crm_meta]"
CRM_META_END = "[/crm_meta]"
logger = logging.getLogger("bens-crm-worker.suitecrm")


def _post(rest_url: str, payload: dict[str, Any], timeout: int = 10) -> dict[str, Any]:
    response = requests.post(rest_url, data=payload, timeout=timeout)
    response.raise_for_status()
    return response.json()


def login(crm: CRMConfig) -> str:
    payload = {
        "method": "login",
        "input_type": "JSON",
        "response_type": "JSON",
        "rest_data": json.dumps(
            {
                "user_auth": {
                    "user_name": crm.user,
                    "password": hashlib.md5(crm.password.encode()).hexdigest(),
                },
                "application_name": "intake",
            }
        ),
    }

    result = _post(crm.rest_url, payload, timeout=10)
    if "id" not in result:
        raise RuntimeError(f"login_failed: {result}")

    return str(result["id"])


def split_description_and_meta(raw_description: str) -> tuple[str, dict[str, str]]:
    if CRM_META_START not in raw_description or CRM_META_END not in raw_description:
        return raw_description, {}

    start = raw_description.rfind(CRM_META_START)
    end = raw_description.rfind(CRM_META_END)
    if start < 0 or end < start:
        return raw_description, {}

    visible = raw_description[:start]
    meta_blob = raw_description[start + len(CRM_META_START):end]

    try:
        parsed = json.loads(meta_blob)
    except Exception:
        return raw_description, {}

    if not isinstance(parsed, dict):
        return raw_description, {}

    meta: dict[str, str] = {}
    for key, value in parsed.items():
        if isinstance(key, str) and (isinstance(value, str) or value is None):
            meta[key] = "" if value is None else value

    return visible, meta


def row_name_parts(row: dict[str, Any], crm_fields: dict[str, str]) -> tuple[str, str]:
    first_name = (crm_fields.get("first_name") or "").strip()
    last_name = (crm_fields.get("last_name") or "").strip()
    if first_name != "" or last_name != "":
        fallback_last = (row.get("company") or "Unknown").strip() if isinstance(row.get("company"), str) else "Unknown"
        return first_name, last_name or fallback_last or "Unknown"

    full_name = (row.get("name") or "").strip() if isinstance(row.get("name"), str) else ""
    if full_name == "":
        fallback_last = (row.get("company") or "Unknown").strip() if isinstance(row.get("company"), str) else "Unknown"
        return "", fallback_last or "Unknown"

    parts = full_name.split()
    if len(parts) == 1:
        return "", parts[0]

    return parts[0], " ".join(parts[1:])


def set_lead(session: str, crm: CRMConfig, row: dict[str, Any]) -> str:
    description, crm_fields = split_description_and_meta((row["description"] or ""))
    first_name, last_name = row_name_parts(row, crm_fields)
    lead = {
        "first_name": first_name,
        "last_name": last_name,
        "account_name": crm_fields.get("account_name") or row["company"],
        "email1": crm_fields.get("email1") or row["email"],
        "phone_work": crm_fields.get("phone_work") or row["phone"] or "",
        "industry": row["industry"] or "",
        "description": description or "",
        "lead_source": "Web Site",
        "status": "New",
        "title": crm_fields.get("title") or "",
        "department": crm_fields.get("department") or "",
    }

    if crm_fields.get("website"):
        lead["website"] = crm_fields["website"]

    if crm_fields.get("campaign_name"):
        lead["campaign_name"] = crm_fields["campaign_name"]
    if crm_fields.get("delivery_tier_c"):
        lead["delivery_tier_c"] = crm_fields["delivery_tier_c"]
    if crm_fields.get("request_id_c"):
        lead["request_id_c"] = crm_fields["request_id_c"]
    if crm_fields.get("product_code_c"):
        lead["product_code_c"] = crm_fields["product_code_c"]
    if crm_fields.get("scan_status_c"):
        lead["scan_status_c"] = crm_fields["scan_status_c"]

    assigned_user_id = crm_fields.get("assigned_user_id") or crm.assigned_user_id
    if assigned_user_id:
        lead["assigned_user_id"] = assigned_user_id

    logger.info(
        "suitecrm lead write fields=%s",
        json.dumps(
            {
                "email1": lead.get("email1", ""),
                "account_name": lead.get("account_name", ""),
                "title": lead.get("title", ""),
                "department": lead.get("department", ""),
                "campaign_name": lead.get("campaign_name", ""),
                "delivery_tier_c": lead.get("delivery_tier_c", ""),
                "request_id_c": lead.get("request_id_c", ""),
                "product_code_c": lead.get("product_code_c", ""),
                "scan_status_c": lead.get("scan_status_c", ""),
            },
            ensure_ascii=True,
            separators=(",", ":"),
        ),
    )

    payload = {
        "method": "set_entry",
        "input_type": "JSON",
        "response_type": "JSON",
        "rest_data": json.dumps(
            {
                "session": session,
                "module_name": "Leads",
                "name_value_list": [{"name": key, "value": value} for key, value in lead.items()],
            }
        ),
    }

    result = _post(crm.rest_url, payload, timeout=10)
    if "id" not in result:
        raise RuntimeError(f"set_entry_failed: {result}")

    return str(result["id"])
