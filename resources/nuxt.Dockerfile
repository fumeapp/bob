FROM public.ecr.aws/lambda/nodejs:14
# FROM public.ecr.aws/a8t0x0j9/fume-node:latest
COPY nuxt.config.js /var/task/nuxt.config.js
COPY .nuxt /var/task/.nuxt
COPY .fume /var/task/.fume
COPY static /var/task/static
COPY node_modules /var/task/node_modules
CMD [ ".fume/fume.index" ]
