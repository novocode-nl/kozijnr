"use client"

import { useRouter } from "next/navigation"

import { cn } from "@/lib/utils"
import { login } from "@/lib/api"
import { loginSchema, type LoginFormValues } from "@/lib/schemas/login"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { Field, FieldDescription, FieldSeparator } from "@/components/ui/field"
import { AppleIcon, GoogleIcon } from "@/components/social-icons"
import { LoginSidePanel } from "@/components/login-side-panel"
import { ConfigForm } from "@/components/config-form/config-form"
import type { FieldConfig } from "@/lib/forms/types"

/**
 * Built on the generic, config-driven `<ConfigForm>`. A failed login
 * returns a generic `message` (no `fieldErrors` — the backend intentionally
 * doesn't distinguish "unknown email" from "wrong password"), which
 * `<ConfigForm>` surfaces as the shared banner.
 */
const fields: FieldConfig<LoginFormValues>[] = [
  {
    name: "email",
    label: "E-mailadres",
    type: "email",
    placeholder: "naam@bedrijf.nl",
    autoComplete: "email",
  },
  {
    name: "password",
    label: "Wachtwoord",
    type: "password",
    autoComplete: "current-password",
  },
]

const defaultValues: LoginFormValues = { email: "", password: "" }

export function LoginForm({
  className,
  ...props
}: React.ComponentProps<"div">) {
  const router = useRouter()

  return (
    <div className={cn("flex flex-col gap-6", className)} {...props}>
      <Card className="overflow-hidden p-0">
        <CardContent className="grid p-0 md:grid-cols-2">
          <div className="flex flex-col gap-6 p-6 md:p-8">
            <div className="flex flex-col items-center gap-2 text-center">
              <h1 className="text-2xl font-bold">Welkom terug</h1>
              <p className="text-balance text-muted-foreground">
                Log in op je Kozijnr-account
              </p>
            </div>
            {/* Disabled: no Apple/Google login backend yet, placeholder only. */}
            <Field className="grid grid-cols-2 gap-4">
              <Button variant="outline" type="button" disabled>
                <AppleIcon />
                Apple
              </Button>
              <Button variant="outline" type="button" disabled>
                <GoogleIcon />
                Google
              </Button>
            </Field>
            <FieldSeparator className="*:data-[slot=field-separator-content]:bg-card">
              Of ga verder met
            </FieldSeparator>
            <ConfigForm
              fields={fields}
              schema={loginSchema}
              defaultValues={defaultValues}
              action={login}
              onSuccess={() => router.push("/dashboard")}
              submitLabel="Inloggen"
              pendingLabel="Bezig met inloggen..."
            />
            <FieldDescription className="text-center">
              <a href="#">Wachtwoord vergeten?</a>
            </FieldDescription>
          </div>
          <LoginSidePanel />
        </CardContent>
      </Card>
    </div>
  )
}
