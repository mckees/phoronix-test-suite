#!/bin/sh

tar -xf oneDNN-1.3.tar.gz

cd oneDNN-1.3/

mkdir build 
cd build 
cmake -DCMAKE_BUILD_TYPE=Release MKLDNN_ARCH_OPT_FLAGS="${CFLAGS}" $CMAKE_OPTIONS ..
make -j $NUM_CPU_CORES
echo $? > ~/install-exit-status

cd ~

echo "#!/bin/bash
export DNNL_CPU_RUNTIME=OMP
export OMP_PLACES=cores
export OMP_PROC_BIND=close
cd oneDNN-1.3/build/tests/benchdnn
./benchdnn --engine=cpu --mode=p \$1 \$3 \$2 > \$LOG_FILE 2>&1
echo \$? > ~/test-exit-status" > mkl-dnn
chmod +x mkl-dnn
