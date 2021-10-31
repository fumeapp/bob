FROM public.ecr.aws/lambda/nodejs:14
# FROM public.ecr.aws/a8t0x0j9/fume-node:latest
COPY fume.js /var/task
COPY server /var/task
CMD [ "fume.handler" ]
