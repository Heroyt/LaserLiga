#!/bin/bash
set -euo pipefail

ERROR_LOG="backup_error.log"

log_error() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - [ERROR] - $@" >> "$ERROR_LOG"
}

trap 'log_error "Error on line $LINENO."' ERR

usage() {
    echo "Usage: $0 [-e env_file] [-o output_dir] [-l error_log_file]"
    exit 1
}

ENV_PATH=""
OUTPUT_DIR=""
CUSTOM_ERROR_LOG=""
while getopts "e:o:l:" opt; do
    case ${opt} in
        e )
            ENV_PATH="$OPTARG"
            ;;
        o )
            OUTPUT_DIR="$OPTARG"
            ;;
        l )
            CUSTOM_ERROR_LOG="$OPTARG"
            ;;
        * )
            usage
            ;;
    esac
done
shift $((OPTIND-1))

if [ -n "${CUSTOM_ERROR_LOG:-}" ]; then
  ERROR_LOG="${CUSTOM_ERROR_LOG}"
fi

if [ -z "${ENV_PATH:-}" ]; then
  ENV_PATH="$(dirname "$0")/.env"
fi

if [ -f "${ENV_PATH}" ]; then
  source "${ENV_PATH}"
  echo "Loaded environment variables from ${ENV_PATH}"
else
  echo "No .env file found at ${ENV_PATH}"
fi

# Verify required environment variables are set
: "${DB_USER:?DB_USER is not set}"
: "${DB_PASSWORD:?DB_PASSWORD is not set}"
: "${DB_NAME:?DB_NAME is not set}"
: "${STATIC_FILES_PATTERN:?STATIC_FILES_PATTERN is not set}"
: "${S3_BUCKET:?S3_BUCKET is not set}"
: "${S3_REGION:?S3_REGION is not set}"
S3_STORAGE_CLASS="${S3_STORAGE_CLASS:-STANDARD}"
BACKUP_PREFIX="${BACKUP_PREFIX:-}"
STATIC_FILES_EXCLUDE="${STATIC_FILES_EXCLUDE:-}"

# Optional S3_ENDPOINT for non-AWS providers
ENDPOINT_OPTION=""
if [ -n "${S3_ENDPOINT:-}" ]; then
  ENDPOINT_OPTION="--endpoint-url ${S3_ENDPOINT}"
fi

# Export S3 credentials if provided
if [ -n "${S3_KEY:-}" ] && [ -n "${S3_SECRET:-}" ]; then
  export AWS_ACCESS_KEY_ID="${S3_KEY}"
  export AWS_SECRET_ACCESS_KEY="${S3_SECRET}"
fi

if [ -z "${ENV_PATH:-}" ]; then
  ENV_PATH="$(dirname "$0")/.env"
fi

if [ -n "${OUTPUT_DIR:-}" ]; then
  TMP_DIR="${OUTPUT_DIR}"
  if [ ! -d "${TMP_DIR}" ]; then
    mkdir -p "${TMP_DIR}" || { log_error "Failed to create temp directory ${TMP_DIR}"; exit 1; }
  fi
else
  TMP_DIR=$(mktemp -d) # By default, create a temporary directory
fi
echo "Using output directory: ${TMP_DIR}"

DB_BACKUP_FILE="${TMP_DIR}/db.sql.gz"
STATIC_BACKUP_FILE="${TMP_DIR}/static.tar.gz"

# Create a gzip-compressed database backup
echo "Creating database backup..."
mysqldump -u "${DB_USER}" --password="${DB_PASSWORD}" "${DB_NAME}" | gzip > "${DB_BACKUP_FILE}"

# Create a gzip-compressed backup of static files using the provided pattern
echo "Creating static files backup..."
if [ -n "${STATIC_FILES_EXCLUDE}" ]; then
  TAR_EXCLUDE="--exclude=${STATIC_FILES_EXCLUDE}"
else
  TAR_EXCLUDE=""
fi
static_files_log=$(tar ${TAR_EXCLUDE} -czvf "${STATIC_BACKUP_FILE}" ${STATIC_FILES_PATTERN})
echo "Static files added to backup:"
echo "${static_files_log}"

# Function to compute MD5 hash (macOS uses md5 -q)
compute_md5() {
    md5sum "$1" | awk '{print $1}'
}

# Function to retrieve the ETag from S3 (removes surrounding quotes)
get_s3_etag() {
  local output
  output=$(aws s3api head-object --bucket "${S3_BUCKET}" --key "$1" --region "${S3_REGION}" ${ENDPOINT_OPTION} 2>&1 || true)
  local etag
  etag=$(echo "$output" | grep ETag | sed -rn 's/.*"ETag": "(\\")?([a-f0-9]+)(-[0-9]+)?(\\")?".*/\2/pg')
  if [[ -z "$etag" && "$output" != *"Not Found"* ]]; then
      log_error "aws s3api head-object error for key $1: $output"
  fi
  echo "$etag"
}

# Function to upload the file to S3 only if the hash is different
upload_if_changed() {
    local local_file="$1"
    local s3_key="${BACKUP_PREFIX}$2"
    local local_hash
    local s3_hash

    echo "Computing hash ${local_file}..."
    local_hash=$(compute_md5 "${local_file}")
    echo "Retrieving ETag for ${S3_BUCKET}/${s3_key}..."
    s3_hash=$(get_s3_etag "${s3_key}")

    echo "Local hash for ${s3_key}: ${local_hash}"
    echo "S3 hash for ${s3_key}: ${s3_hash:-not available}"

    if [ "${local_hash}" != "${s3_hash}" ]; then
        echo "Hash differs or the file does not exist on S3, uploading ${s3_key}..."
        aws s3 cp "${local_file}" "s3://${S3_BUCKET}/${s3_key}" --storage-class "${S3_STORAGE_CLASS}" --region "${S3_REGION}" ${ENDPOINT_OPTION}
    else
        echo "No change detected for ${s3_key}, skipping upload."
    fi
}

# Upload both backups
upload_if_changed "${DB_BACKUP_FILE}" "$(basename "${DB_BACKUP_FILE}")"
upload_if_changed "${STATIC_BACKUP_FILE}" "$(basename "${STATIC_BACKUP_FILE}")"

# Clean up the temporary directory if not using OUTPUT_DIR
if [ -z "${OUTPUT_DIR:-}" ]; then
  rm -rf "${TMP_DIR}"
fi
echo "Backup completed."
