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

rsync -av --progress ./src/docker.versions/ "$tmpdir" --exclude .git --exclude tmp --exclude .env --exclude archive

cd "$tmpdir" || exit

tar -cJf "$filename" .

cd - || exit

rm -rf "$tmpdir"

sed -i '' 's/<!ENTITY version ".*">/<!ENTITY version "'"$version"'">/' docker.versions.plg
md5hash=$(md5 -q "$filename")
sed -i '' 's/<!ENTITY md5 ".*">/<!ENTITY md5 "'"$md5hash"'">/' docker.versions.plg

echo "MD5: $(md5sum "$filename")"
