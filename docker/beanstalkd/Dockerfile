FROM ubuntu:20.04

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update
RUN apt-get install -y beanstalkd

RUN sed -i 's/\#START=yes/START=yes/g' /etc/default/beanstalkd

ENTRYPOINT ["beanstalkd", "-l", "0.0.0.0", "-p", "11300","-b","/var/lib/beanstalkd"]
# ENTRYPOINT ["tail", "-f", "/dev/null"]