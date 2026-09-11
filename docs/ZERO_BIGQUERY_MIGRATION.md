# EWMS zero-BigQuery analytics migration

## Outcome

EWMS reads GA4 and GSC summaries from its own PostgreSQL database. A Laravel
scheduled command obtains reporting data from the GA4 Data API and Search
Console API, then Redis caches dashboard responses. BigQuery is not used by
the dashboard or collector.

Keep `ewms-bigquery-reader` disabled until this migration is validated.

## Google identity

Create a dedicated service account with no Google Cloud IAM roles:

```bash
gcloud iam service-accounts create ewms-analytics-api \
  --project=burnished-stone-421212 \
  --display-name='EWMS Analytics API Reader'

gcloud services enable analyticsdata.googleapis.com searchconsole.googleapis.com \
  --project=burnished-stone-421212

gcloud iam service-accounts keys create ewms-analytics-reader.json \
  --project=burnished-stone-421212 \
  --iam-account=ewms-analytics-api@burnished-stone-421212.iam.gserviceaccount.com
```

The service account needs no BigQuery role. Grant its email Viewer access at
the parent Google Analytics account when all GA4 properties share that
account; otherwise grant Viewer on each property. Grant it access to each
Search Console property as well.

Place the JSON key on the server at
`storage/app/gcp/analytics-reader.json`, permissions `0600`. Never commit it.

## Environment

```dotenv
BIGQUERY_PROJECT_ID=
ANALYTICS_API_ENABLED=true
ANALYTICS_GOOGLE_CREDENTIALS_PATH=storage/app/gcp/analytics-reader.json
ANALYTICS_API_TIMEOUT=30
ANALYTICS_API_REQUEST_DELAY_MS=150
```

## Deploy

Back up the database and application directory first. Then rebuild the EWMS
containers and run the migration:

```bash
docker compose build app queue scheduler
docker compose run --rm app php artisan migrate --force
docker compose up -d
docker compose exec app php artisan optimize:clear
docker compose restart app queue scheduler
```

Verify how many local websites are mapped:

```bash
docker compose exec app php artisan tinker --execute="dump([\
 'active'=>App\\Models\\Website::where('status','active')->count(),\
 'ga4'=>App\\Models\\Website::whereNotNull('ga4_property_id')->count(),\
 'gsc'=>App\\Models\\Website::whereNotNull('gsc_property')->count(),\
]);"
```

## Pilot

The dry run performs no provider requests or database writes:

```bash
docker compose exec app php artisan ewms:sync-analytics \
  --date=2026-09-05 --website=exoticsenegal.com --dry-run
```

Run the single-site pilot:

```bash
docker compose exec app php artisan ewms:sync-analytics \
  --date=2026-09-05 --website=exoticsenegal.com
```

Inspect the recorded result:

```bash
docker compose exec postgres psql -U "$DB_USERNAME" -d "$DB_DATABASE" -c \
  "SELECT source,data_date,status,rows_written,left(error,120) error FROM analytics_sync_runs ORDER BY id DESC LIMIT 10;"
```

Do not run a full historical backfill until the pilot succeeds for both GA4
and GSC. Backfill in short date ranges; reruns replace each website/day slice
inside a transaction.

## Cutover verification

After a successful pilot, open the EWMS Analytics and Search pages while
watching BigQuery job history. There must be no new job from
`ewms-bigquery-reader`. The daily scheduler processes the three most recent
finalized days at 05:15 and warms Redis from PostgreSQL at 06:30.

After the local database contains the required history and has been backed
up, disable GA4 BigQuery links and unlink billing from the analytics project
if the requirement is an absolute zero future BigQuery bill.
