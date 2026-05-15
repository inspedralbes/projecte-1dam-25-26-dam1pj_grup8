# MongoDB aggregation pipelines (Access logs)

Col·lecció: `access_logs`

Documents d'exemple (estructura mínima):

```js
{
  url: "http://localhost:8080/admin/admin.php",
  method: "GET",
  user: null, // o string si està autenticat
  timestamp: ISODate("2026-05-08T10:00:00Z"),
  browser: {
    userAgent: "...",
    acceptLanguage: "..."
  },
  ip: "172.18.0.1"
}
```

## Filtres comuns

A totes les consultes s'aplica (si escau) un `$match` amb:

- Rang de dates (dia inici/fi):

```js
{ timestamp: { $gte: <start>, $lte: <end> } }
```

- Usuari autenticat:

```js
{ user: "<username>" }
```

- Pàgina (substring / regex, case-insensitive):

```js
{ url: /<text>/i }
```

## 1) Total number of accesses

```js
[
  { $match: <filters> },
  { $count: "total" }
]
```

Implementació: a [php/admin/admin_stats.php](../php/admin/admin_stats.php)

## 2) Most visited pages

Top 5 pàgines per nombre de visites:

```js
[
  { $match: <filters> },
  { $group: { _id: "$url", total: { $sum: 1 } } },
  { $sort: { total: -1 } },
  { $limit: 5 },
  { $project: { _id: 0, page: "$_id", total: 1 } }
]
```

## 3) Most active users

Top 5 usuaris autenticats. S'exclou `user: null`:

```js
[
  { $match: { ...<filters>, user: { $ne: null } } },
  { $group: { _id: "$user", total: { $sum: 1 } } },
  { $sort: { total: -1 } },
  { $limit: 5 },
  { $project: { _id: 0, username: "$_id", total: 1 } }
]
```

## 4) Accesses grouped by day

Per alimentar una gràfica de tendència (line chart). Agrupació per dia en format `YYYY-MM-DD`:

```js
[
  { $match: <filters> },
  {
    $group: {
      _id: {
        $dateToString: {
          format: "%Y-%m-%d",
          date: "$timestamp",
          timezone: "UTC"
        }
      },
      total: { $sum: 1 }
    }
  },
  { $sort: { _id: 1 } },
  { $project: { _id: 0, dia: "$_id", total: 1 } }
]
```

Nota: la zona horària utilitzada és `UTC` (vegeu [php/admin/admin_stats.php](../php/admin/admin_stats.php)).
