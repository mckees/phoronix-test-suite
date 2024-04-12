#!/bin/sh

tar -zxvf ebizzy-0.3.tar.gz

cd ebizzy-0.3/

cc -pthread -lpthread $CFLAGS -o ebizzy ebizzy.c
echo $? > ~/install-exit-status
cd ..

echo "#!/bin/sh
cd ebizzy-0.3/
./ebizzy -S 20 > \$LOG_FILE 2>&1
echo \$? > ~/test-exit-status" > ebizzy
chmod +x ebizzy
