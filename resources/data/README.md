# Italian geo data

- `italian_comuni.json.gz` — ~7900 comuni (ISTAT names + CAP primario + WGS84 coordinates). Sources: [matteocontrini/comuni-json](https://github.com/matteocontrini/comuni-json), [opendatasicilia/comuni-italiani](https://github.com/opendatasicilia/comuni-italiani) (`coordinate.csv`).
- `italian_cities.json` — legacy small subset; used only if the gzip file is missing.

Regenerate gzip after updating upstream CSV/JSON (merge script in project docs / Phase 3 agent notes).
