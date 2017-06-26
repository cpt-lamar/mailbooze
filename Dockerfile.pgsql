ARG BASE_IMAGE
FROM $BASE_IMAGE

ENV LANG=en_US.utf8 \
    PGDATA="/var/lib/postgresql/data"

RUN apk add --update --no-cache postgresql postgresql-client su-exec


ENV  DB_NAME\
     DB_USER\
     DB_PASSWORD

VOLUME $PGDATA

RUN mkdir -p /run/postgresql \
  && chown -R postgres /run/postgresql \
  && mkdir -p $PGDATA

EXPOSE 5432

CMD set -x \
  && { [ -s "$PGDATA/PG_VERSION" ] \
     || { chown -R postgres $PGDATA \
          && su-exec postgres initdb -U postgres --locale=en_US.utf8 \
          && echo -e "local all postgres  trust\nhost $DB_NAME $DB_USER all md5" > $PGDATA/pg_hba.conf \
          && sed -i  -r "s/#?(track_activities|track_counts|autovacuum) = (off|on)/\1 = off/g" $PGDATA/postgresql.conf \
          && su-exec postgres pg_ctl -w start \
          && psql -U postgres -c "CREATE DATABASE $DB_NAME" \
          && psql -U postgres -c "CREATE USER $DB_USER WITH SUPERUSER PASSWORD '$DB_PASSWORD'" \
          && su-exec postgres pg_ctl -w stop ;} \
    ;} \
  && su-exec postgres postgres -c listen_addresses='*'
