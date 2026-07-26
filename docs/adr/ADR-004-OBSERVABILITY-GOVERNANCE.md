# ADR-004: Observability and Engineering Governance

## Status
Accepted

## Context

The project had health checks and basic request metrics, but lacked explicit SLO-oriented indicators and a standardized review checklist in pull requests.

## Decision

1. Expose SLO-ready request indicators through the metrics layer:
- latency percentiles (`p95`, `p99`)
- error rate and availability percentages
- status code family breakdown (`2xx/3xx/4xx/5xx`)
- configurable p95 target (`SLO_API_P95_TARGET_MS`)

2. Standardize PR quality gates with a repository-level pull request template that includes:
- code quality checks (`cs-check`, `phpstan`, `phpunit`)
- security and documentation checks
- rollout/rollback notes

## Consequences

### Positive
- Faster detection of reliability regressions.
- Clear baseline for operational SLO tracking.
- Consistent change review quality across contributors.

### Trade-offs
- Slightly higher query cost in metrics endpoints due to percentile calculation.
- Additional discipline required to keep SLO targets aligned with real operational goals.
