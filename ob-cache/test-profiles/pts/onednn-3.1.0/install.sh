#!/bin/sh
tar -xf oneDNN-3.1.tar.gz
cd oneDNN-3.1
mkdir build 
cd build 
cmake -DCMAKE_BUILD_TYPE=Release MKLDNN_ARCH_OPT_FLAGS="$CFLAGS" $CMAKE_OPTIONS ..
make -j $NUM_CPU_CORES
echo $? > ~/install-exit-status
cd ~
echo "#!/bin/bash
export DNNL_CPU_RUNTIME=OMP
export OMP_PLACES=cores
export OMP_PROC_BIND=close
cd oneDNN-3.1/build/tests/benchdnn
./benchdnn \$4 --mode=p \$1 \$3 \$2 > \$LOG_FILE 2>&1
echo \$? > ~/test-exit-status" > onednn
chmod +x onednn
