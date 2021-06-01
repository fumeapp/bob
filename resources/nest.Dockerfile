FROM public.ecr.aws/lambda/nodejs:14
# FROM public.ecr.aws/a8t0x0j9/fume-node:latest
COPY dist /var/task/dist
COPY node_modules /var/task/node_modules
CMD [ "dist/.fume/fume.index" ]
