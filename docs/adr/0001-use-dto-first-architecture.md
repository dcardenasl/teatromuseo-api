# 1. Use DTO-First Architecture

Date: 2026-03-03

## Status

Accepted

## Context

In modern API development, passing raw arrays or generic framework `Request` objects deep into the service layer creates invisible dependencies, hidden schemas, and validation gaps. Services become coupled to the HTTP context, and determining what data a service actually requires becomes a guessing game, leading to "spaghetti code."

## Decision

We will strictly enforce a **DTO-First (Data Transfer Object) Architecture** across the entire API starter kit:
1. **HTTP boundaries stop at the Controller:** `ApiController` gathers input but immediately maps it to a `BaseRequestDTO`.
2. **Immutability:** All DTOs must use PHP 8.2 `readonly class` to prevent state mutation during service execution.
3. **Self-Validation:** DTO constructors are responsible for validating and sanitizing the incoming data array before the object is created.
4. **Service Purity:** Services must only accept DTOs (e.g. `UserStoreRequestDTO`) and a `SecurityContext`, completely agnostic to HTTP logic.

## Consequences

- **Positive:** Massive reduction in "Fat Controllers" and "Fat Services". Input validation is completely encapsulated. Code is strongly typed and self-documenting.
- **Negative:** Requires creating slightly more boilerplate (Request and Response DTO classes) for every endpoint, which can feel tedious for simple CRUD operations (mitigated by our CLI tools).