#!/bin/sh -e

usage ()
{
  echo 'Usage : backup.sh LOCAL_FOLDER REMOTE_HOST REMOTE_FOLDER'
  echo '          LOCAL_FOLDER must be a btrfs subvolume'
  echo '          REMOTE_HOST is your backup server, with btrfs, sudo, passwordless ssh'
  echo '          REMOTE_FOLDER is where subvolumes will be backed up'
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
LOCAL_FOLDER=$1
REMOTE_HOST=$2
REMOTE_FOLDER=$3

#Misc
SSH_CMD="ssh -o ConnectTimeout=10 -o BatchMode=yes -o StrictHostKeyChecking=no"


######################################################
#Functions
######################################################


function transfer
{
  if [ "$1" != "" ]; then
    SEND_CMD="sudo btrfs send -p $LOCAL_FOLDER/.snapshotz/$1 $LOCAL_FOLDER/.snapshotz/$2"
  else
    SEND_CMD="sudo btrfs send $LOCAL_FOLDER/.snapshotz/$2"
  fi

  $SEND_CMD | $SSH_CMD $REMOTE_HOST sudo btrfs receive $REMOTE_FOLDER/.snapshotz/
  NB_SNAPSHOTS=$(( $NB_SNAPSHOTS +1 ))
}


function find_remote_snapshot
{
  for i in ${!REMOTE_LIST[*]}; do
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
    if [ ${#LOCAL_LIST[*]} -gt $1 ]; then #recurse !
      transfer_snapshots $(( $1 + 1 ))
      BASE_SNAPSHOT=${LOCAL_LIST[$1 +1]}
    else
      BASE_SNAPSHOT=""
    fi
    #do the actual transfer here
    transfer "$BASE_SNAPSHOT" "${LOCAL_LIST[$1]}"
  fi
}


######################################################
#Script start
######################################################


#Starting backup...
echo "Snapshotting $LOCAL_FOLDER"
sudo mkdir -p $LOCAL_FOLDER/.snapshotz/ 
sudo btrfs sub snap -r "$LOCAL_FOLDER" "$LOCAL_FOLDER/.snapshotz/$(date -u +"%Y-%m-%dT%H%M%S%z")"
sync

echo "$LOCAL_FOLDER successfully snapshotted - Sending snapshots to $REMOTE_HOST"

#Prepare synchronization
LOCAL_LIST=($(sudo ls -1r "$LOCAL_FOLDER/.snapshotz/"))
REMOTE_LIST=($($SSH_CMD $REMOTE_HOST "sudo mkdir -p $REMOTE_FOLDER/.snapshotz/ && sudo ls -1r $REMOTE_FOLDER/.snapshotz/" ))
NB_SNAPSHOTS=0

#send snapshot recursively to remote backup server
transfer_snapshots 0

echo "$NB_SNAPSHOTS snapshots successfully sent to $REMOTE_HOST"


#Starting backup...
echo "Cleaning $LOCAL_FOLDER/.snapshotz"
SNAPSHOTS=$(sudo sh -c "ls -1d $LOCAL_FOLDER/.snapshotz/*")

LAST_7_DAYS=($(for i in {1..7}; do date --date="$i days ago" -u +%Y-%m-%d; done ))

#Keep 1 snapshot per day for last 7 days
for DAY in ${LAST_7_DAYS[@]}; do
  echo "$SNAPSHOTS" | grep "$DAY" | tail +2 | xargs -r -n 1 sudo btrfs sub del
done

#Keep 1 snapshot per month for last 2 months - but do not touch last 7 days
EXCLUDE=$(printf ' -e %s' "${LAST_7_DAYS[@]}")
for i in {1..2}; do
  echo "$SNAPSHOTS" | grep "$(date --date="$i months ago" -u +%Y-%m)" | tail +2 | grep -v $EXCLUDE | xargs -r -n 1 sudo btrfs sub del
done
echo "Successully cleaned $LOCAL_FOLDER/.snapshotz"
