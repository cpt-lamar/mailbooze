#!/bin/sh -e

usage ()
{
  echo 'Usage : backup.sh BACKUP_FOLDER REMOTE_HOST REMOTE_FOLDER'
  echo '          BACKUP_FOLDER must be a btrfs subvolume'
  echo '          REMOTE_HOST is your backup server, with btrfs, sudo, passwordless ssh'
  echo '          SNAPSHOT_FOLDER is where subvolumes will be backed up on both hosts'
  exit
}

if [ "$#" -ne 3 ]
then
  usage
fi


######################################################
#Configuration
######################################################


#Configuration for backup script
BACKUP_FOLDER=$1
REMOTE_HOST=$2
SNAPSHOT_FOLDER=$3

#Misc
SSH_CMD="ssh -o ConnectTimeout=10 -o BatchMode=yes -o StrictHostKeyChecking=no"


######################################################
#Functions
######################################################


function transfer
{
  if [ "$1" != "empty" ]; then
    SEND_CMD="sudo btrfs send -p $SNAPSHOT_FOLDER/$1 $SNAPSHOT_FOLDER/$2"
  else
    SEND_CMD="sudo btrfs send $SNAPSHOT_FOLDER/$2"
  fi

  #$SEND_CMD | pv -L 10M | $SSH_CMD $REMOTE_HOST sudo btrfs receive $SNAPSHOT_FOLDER/
  $SEND_CMD | $SSH_CMD $REMOTE_HOST sudo btrfs receive $SNAPSHOT_FOLDER/
  NB_SNAPSHOTS=$(( $NB_SNAPSHOTS +1 ))
}


function find_remote_snapshot
{
  for i in ${!REMOTE_LIST[@]}; do
    if [ "$1" == "${REMOTE_LIST[$i]}" ]; then
      echo $i
      return
    fi
  done
  echo -1
}


function transfer_snapshots
{
  res=$( find_remote_snapshot ${LOCAL_LIST[$1]} ) 
  if [ $res -ge 0 ]; then
    echo "found ${LOCAL_LIST[$1]} on remote server"
    return 0
  else
    if [ $1 -lt $(( ${#LOCAL_LIST[@]} -1)) ]; then #recurse !
      transfer_snapshots $(( $1 + 1 ))
      BASE_SNAPSHOT=${LOCAL_LIST[$1 +1]}
    else
      BASE_SNAPSHOT="empty"
    fi
    #do the actual transfer here
    transfer "$BASE_SNAPSHOT" "${LOCAL_LIST[$1]}"
  fi
}


######################################################
#Script start
######################################################


#Starting backup...
echo "Snapshotting $BACKUP_FOLDER"
sudo mkdir -p $SNAPSHOT_FOLDER/ 
sudo btrfs sub snap -r "$BACKUP_FOLDER" "$SNAPSHOT_FOLDER/$(date -u +"%Y-%m-%dT%H%M%S%z")"
sync

echo "$BACKUP_FOLDER successfully snapshotted - Sending snapshots to $REMOTE_HOST"

#Prepare synchronization
LOCAL_LIST=($(sudo ls -1r "$SNAPSHOT_FOLDER/"))
REMOTE_LIST=($($SSH_CMD $REMOTE_HOST "sudo mkdir -p $SNAPSHOT_FOLDER/ && sudo ls -1r $SNAPSHOT_FOLDER/" ))
NB_SNAPSHOTS=0

#send snapshot recursively to remote backup server
transfer_snapshots 0

echo "$NB_SNAPSHOTS snapshots successfully sent to $REMOTE_HOST"

#Starting cleanup...
echo "Cleaning $SNAPSHOT_FOLDER/"

#Keep 1 snapshot per day for last 7 days
LAST_7_DAYS=($(for i in {1..7}; do date --date="$i days ago" -u +%Y-%m-%d; done ))
for DAY in ${LAST_7_DAYS[@]}; do
  printf '%s\n' ${LOCAL_LIST[@]} | sort | grep "$DAY" | tail +2 | xargs -r -n 1 sh -c "sudo btrfs sub del $SNAPSHOT_FOLDER/\$0"
  printf '%s\n' ${REMOTE_LIST[@]} | sort | grep "$DAY" | tail +2 | xargs -r -n 1 sh -c "$SSH_CMD $REMOTE_HOST sudo btrfs sub del $SNAPSHOT_FOLDER/\$0"
done

#Keep 1 snapshot per month for previous month - but do not touch last 7 days
LASTMONTH=$(date --date="$(date +%Y-%m-15) - 1 month" -u +%Y-%m)
EXCLUDE="-e $(date -u +%Y-%m-%d) $(printf ' -e %s' "${LAST_7_DAYS[@]}")"
printf '%s\n' ${LOCAL_LIST[@]} | sort | grep "$LASTMONTH" | tail +2 | grep -v $EXCLUDE | xargs -r -n 1 sh -c "sudo btrfs sub del $SNAPSHOT_FOLDER/\$0"
printf '%s\n' ${REMOTE_LIST[@]} | sort | grep "$LASTMONTH" | tail +2 | grep -v $EXCLUDE | xargs -r -n 1 sh -c "$SSH_CMD $REMOTE_HOST sudo btrfs sub del $SNAPSHOT_FOLDER/\$0"

echo "Successully cleaned $SNAPSHOT_FOLDER/"


