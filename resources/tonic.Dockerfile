FROM amazon/aws-lambda-go:latest
COPY main ${LAMBDA_TASK_ROOT}/main
CMD [ "main" ]
