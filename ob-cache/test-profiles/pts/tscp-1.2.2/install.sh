#!/bin/sh

tar -xjvf tscp181_pts.tar.bz2
cd tscp181/

cc $CFLAGS *.c -o tscp
echo $? > ~/install-exit-status

cd ~

echo "#!/bin/sh
cd tscp181/
./tscp \$@ > \$LOG_FILE 2>&1
echo \$? > ~/test-exit-status" > tscp
chmod +x tscp
