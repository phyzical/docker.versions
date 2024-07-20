#!/bin/bash

CWD=$(pwd)
tmpdir="$CWD/tmp/tmp.$(($RANDOM * 19318203981230 + 40))"
version=$(date +"%Y.%m.%d")
filename="$CWD/archive/docker.versions-$version.txz"
rm "$filename"
dayversion=$(ls "$CWD"/archive/docker.versions-"$version"*.txz 2>/dev/null | wc -l)

if [ "$dayversion" -gt 0 ]; then
    filename=$CWD/archive/docker.versions-$version.$dayversion.txz
fi
mkdir -p "$tmpdir"

rsync -av --progress . "$tmpdir" --exclude .git --exclude tmp --exclude .env --exclude archive

cd "$tmpdir" || exit

tar -cJf "$filename" .

cd - || exit

rm -rf "$tmpdir"

echo "MD5: $(md5sum "$filename")"
